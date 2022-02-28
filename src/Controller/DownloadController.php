<?php declare(strict_types=1);

namespace Statistics\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Api\Adapter\MediaAdapter;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\Mvc\Exception;
use Statistics\Api\Adapter\HitAdapter;

/**
 * The download controller class.
 *
 * Count direct download of a file.
 *
 * @see \AccessResource\Controller\AccessResourceController
 */
class DownloadController extends AbstractActionController
{
    /**
     * @var \Omeka\Api\Adapter\MediaAdapter;
     */
    protected $mediaAdapter;

    /**
     * @var ?\Statistics\Api\Adapter\HitAdapter;
     */
    protected $hitAdapter;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var Media
     */
    protected $media;

    /**
     * @var string
     */
    protected $storageType;

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $mediaType;

    /**
     * @var string
     */
    protected $filepath;

    /**
     * @var int
     */
    protected $fileSize;

    public function __construct(
        MediaAdapter $mediaAdapter,
        ?HitAdapter $hitAdapter,
        string $basePath
    ) {
        $this->mediaAdapter = $mediaAdapter;
        $this->hitAdapter = $hitAdapter;
        $this->basePath = $basePath;
    }

    /**
     * Forward to the 'files' action
     *
     * @see self::filesAction()
     */
    public function indexAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'files';
        return $this->forward()->dispatch('Statistics\Controller\Download', $params);
    }

    /**
     * Check file and prepare it to be sent.
     */
    public function filesAction()
    {
        // When the media is private for the user, it is not available, in any
        // case. This check is done automatically directly at database level.
        $resource = $this->prepareMedia();
        // There may be a resource, but not a file.
        if (!$resource) {
            throw new Exception\NotFoundException;
        }

        if (!$this->isAdminRequest()) {
            $this->logCurrentFile();
        }

        $this->sendFile();
    }

    /**
     * Log the hit on the current file.
     */
    protected function logCurrentFile(): void
    {
        $includeBots = (bool) $this->settings()->get('statistics_include_bots');
        if (empty($includeBots)) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($this->hitAdapter->isBot($userAgent)) {
                return;
            }
        }

        $data = [
            // TODO Only local storage is logged currently.
            'o:url' => '/files/' . $this->storageType . '/' . $this->filename,
            'o:entity_id' => $this->media->getId(),
            'o:entity_name' => 'media',
        ];

        $request = new Request(Request::CREATE, 'hits');
        $request
            ->setContent($data)
            ->setOption('initialize', false)
            ->setOption('finalize', false)
            ->setOption('returnScalar', 'id')
        ;
        // The entity manager is automatically flushed by default.
        $this->hitAdapter->create($request);
    }

    /**
     * Send file as stream.
     */
    protected function sendFile()
    {
        // Everything has been checked.
        $dispositionMode = 'inline';

        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $this->getResponse();
        // Write headers.
        $response->getHeaders()
            ->addHeaderLine(sprintf('Content-Type: %s', $this->mediaType))
            ->addHeaderLine(sprintf('Content-Disposition: %s; filename="%s"', $dispositionMode, pathinfo($this->filepath, PATHINFO_BASENAME)))
            ->addHeaderLine(sprintf('Content-Length: %s', $this->fileSize))
            ->addHeaderLine('Content-Transfer-Encoding: binary')
            // Use this to open files directly.
            // Cache for 30 days.
            ->addHeaderLine('Cache-Control: private, max-age=2592000, post-check=2592000, pre-check=2592000')
            ->addHeaderLine(sprintf('Expires: %s', gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT'));

        // Send headers separately to handle large files.
        $response->sendHeaders();

        // TODO Use Laminas stream response.

        // Clears all active output buffers to avoid memory overflow.
        $response->setContent('');
        while (ob_get_level()) {
            ob_end_clean();
        }
        readfile($this->filepath);

        // TODO Fix issue with session. See readme of module XmlViewer.
        ini_set('display_errors', '0');

        // Return response to avoid default view rendering and to manage events.
        return $response;
    }

    /**
     * Check if the request is fine and save results.
     */
    protected function prepareMedia(): ?Media
    {
        $this->media = null;
        $this->storageType = null;
        $this->filename = null;
        $this->mediaType = null;
        $this->filepath = null;
        $this->fileSize = null;

        $this->filename = $this->params()->fromRoute('filename');
        if (!$this->filename) {
            return null;
        }

        // For compatibility with module ArchiveRepertory, don't take the
        // filename, but remove the extension.
        // $storageId = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($this->filename, PATHINFO_EXTENSION);
        $storageId = mb_strlen($extension)
            ? mb_substr($this->filename, 0, -mb_strlen($extension) - 1)
            : $this->filename;

        // "storage_id" is not available through default api, so use core entity
        // manager. Nevertheless, the call to the api allows to check rights.
        if (!$storageId) {
            return null;
        }

        try {
            $this->media = $this->mediaAdapter->findEntity(['storageId' => $storageId]);
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return null;
        }

        $this->storageType = $this->params()->fromRoute('type');
        if ($this->storageType === 'original') {
            if (!$this->media->hasOriginal()) {
                return null;
            }
            // Reset the filename for case sensitivity purpose.
            $this->filename = $this->media->getFilename();
        } elseif (!$this->media->hasThumbnails()) {
            return null;
        } else {
            $this->filename = $storageId . '.jpg';
        }

        $this->filepath = sprintf('%s/%s/%s', $this->basePath, $this->storageType, $this->filename);
        if (!is_readable($this->filepath)) {
            return null;
        }

        if ($this->storageType === 'original') {
            $this->fileSize = (int) $this->media->getSize();
            $this->mediaType = $this->media->getMediaType();
        } else {
            $this->fileSize = (int) filesize($this->filepath);
            $this->mediaType = 'image/jpeg';
        }

        return $this->media;
    }

    /**
     * Check if the file is fetched from an admin front-end.
     */
    protected function isAdminRequest(): bool
    {
        // It's not simple to determine from server if the request comes from
        // a visitor on the site or something else.
        // So use the referrer and the identity.
        if (!$this->identity()) {
            return false;
        }
        $referrer = (string) $this->getRequest()->getServer('HTTP_REFERER');
        if (!$referrer) {
            return false;
        }
        $urlAdminTop = $this->url()->fromRoute('admin', [], ['force_canonical' => true]) . '/';
        return strpos($referrer, $urlAdminTop) === 0;
    }

    /**
     * Redirect to previous page.
     */
    protected function gotoPreviousPage()
    {
        return $this->redirect()->toUrl(
            $this->getRequest()->getServer('HTTP_REFERER')
               ?: $this->url()->fromRoute('top')
        );
    }
}
