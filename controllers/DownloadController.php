<?php
/**
 * The download controller class.
 *
 * Count direct download of a file.
 *
 * @package Stats
 */
class Stats_DownloadController extends Omeka_Controller_AbstractActionController
{
    protected $_type;
    protected $_storage;
    protected $_filename;
    protected $_filepath;
    protected $_filesize;
    protected $_file;
    protected $_mode;
    protected $_theme;

    /**
     * Forward to the 'files' action
     *
     * @see self::filesAction()
     */
    public function indexAction()
    {
        $this->_forward('files');
    }

    /**
     * Check file and prepare it to be sent.
     */
    public function filesAction()
    {
        // No view for this action.
        $this->_helper->viewRenderer->setNoRender();

        // Check post and throw to previous page in case of problem.
        if (!$this->_checkPost()) {
            $this->_helper->flashMessenger(__("This file doesn't exist."), 'error');
            return $this->_gotoPreviousPage();
        }

        if ($this->_getTheme() == 'public') {
            $this->_logCurrentFile();
        }

        $this->_sendFile();
    }

    /**
     * Log the hit on the current file.
     */
    protected function _logCurrentFile()
    {
        $hit = new Hit;
        $hit->setCurrentRequest();
        // The redirect to "download/files" is not useful: keep original url.
        $hit->url = '/files/' . $this->_type . '/' . $this->_filename;
        // No filter is needed because the record is known.
        $hit->setRecord($this->_file);
        $hit->setCurrentUser();
        $hit->save();
    }

    /**
     * Helper to send file as stream or attachment.
     */
    protected function _sendFile()
    {
        // Everything has been checked.
        $filepath = $this->_getFilepath();
        $filesize = $this->_getFilesize();
        $file = $this->_getFile();
        $contentType = $this->_getContentType();
        $mode = $this->_getMode();

        $this->getResponse()->clearBody();
        $this->getResponse()->setHeader('Content-Disposition', $mode . '; filename="' . pathinfo($filepath, PATHINFO_BASENAME) . '"', true);
        $this->getResponse()->setHeader('Content-Type', $contentType);
        $this->getResponse()->setHeader('Content-Length', $filesize);
        // Cache for 30 days.
        $this->getResponse()->setHeader('Cache-Control', 'private, max-age=2592000, post-check=2592000, pre-check=2592000', true);
        $this->getResponse()->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT', true);
        $file = file_get_contents($filepath);
        $this->getResponse()->setBody($file);
    }

    /**
     * Check if the post is good and save results.
     *
     * @return boolean
     */
    protected function _checkPost()
    {
        if (!$this->_getStorage()) {
            return false;
        }

        if (!$this->_getFilename()) {
            return false;
        }

        if (!$this->_getFilepath()) {
            return false;
        }

        if (!$this->_getFilesize()) {
            return false;
        }

        if (!$this->_getFile()) {
            return false;
        }

        if (!$this->_getContentType()) {
            return false;
        }

        if (!$this->_getMode()) {
            return false;
        }

        return true;
    }

    /**
     * Get and set type (generally original, sometimes fullsize).
     *
     * @internal The type is not checked, but if not authorized, storage will
     * return an error.
     *
     * @return string ("original" by default)
     */
    protected function _getType()
    {
        if (is_null($this->_type)) {
            $this->_type = $this->getRequest()->getParam('type');

            // Default type.
            if (empty($this->_type)) {
                $this->_type = 'original';
            }
        }

        return $this->_type;
    }

    /**
     * Get, check and set type of storage.
     *
     * @return string Path to the storage of the selected type of file.
     */
    protected function _getStorage()
    {
        if (is_null($this->_storage)) {
            $type = $this->_getType();

            // This is used to get list of storage path. Is there a better way?
            // getPathByType() is not secure.
            $file = new File;
            try {
                $storagePath = $file->getStoragePath($type);
            } catch (RuntimeException $e) {
                $this->_storage = false;
                return false;
            }
            $this->_storage = ($type == 'original')
                ? substr($storagePath, 0, strlen($storagePath) - 1)
                : substr($storagePath, 0, strlen($storagePath) - strlen(File::DERIVATIVE_EXT) - 2);
        }

        return $this->_storage;
    }

    /**
     * Get and set filename.
     *
     * @internal The filename is not checked, but if not existing, filepath will
     * return an error.
     *
     * @return string Filename.
     */
    protected function _getFilename()
    {
        if (is_null($this->_filename)) {
            $this->_filename = $this->getRequest()->getParam('filename');
        }

        return $this->_filename;
    }

    /**
     * Get and set filepath.
     *
     * @return string Path to the file.
     */
    protected function _getFilepath()
    {
        if (is_null($this->_filepath)) {
            $filename = $this->_getFilename();
            $storage = $this->_getStorage();
            $storagePath = FILES_DIR . DIRECTORY_SEPARATOR . $this->_storage . DIRECTORY_SEPARATOR;
            $filepath = realpath($storagePath . $filename);
            if (strpos($filepath, $storagePath) !== 0) {
                return false;
            }
            $this->_filepath = $filepath;
        }

        return $this->_filepath;
    }

    /**
     * Get and set file size. This allows to check if file really exists.
     *
     * @return integer Length of the file.
     */
    protected function _getFilesize()
    {
        if (is_null($this->_filesize)) {
            $filepath = $this->_getFilepath();
            $this->_filesize = @filesize($filepath);
        }

        return $this->_filesize;
    }

    /**
     * Set and get file object from the filename. Rights access are checked.
     *
     * @return File|null
     */
    protected function _getFile()
    {
        if (is_null($this->_file)) {
            $filename = $this->_getFilename();
            if ($this->_getStorage() == 'original') {
                $this->_file =  get_db()->getTable('File')->findBySql('filename = ?', array($filename), true);
            }
           // Get a derivative: this is functional only because filenames are
           // hashed.
            else {
                $originalFilename = substr($filename, 0, strlen($filename) - strlen(File::DERIVATIVE_EXT) - 1);
                $this->_file = get_db()->getTable('File')->findBySql('filename LIKE ?', array($originalFilename . '%'), true);
            }

            // Check rights: if the file belongs to a public item.
            if (empty($this->_file)) {
                $this->_file = false;
            }
            else {
                $item = $this->_file->getItem();
                if (empty($item)) {
                    $this->_file = false;
                }
            }
        }

        return $this->_file;
     }

    /**
     * Set and get file object from the filename. Rights access are checked.
     *
     * @return File|null
     */
    protected function _getContentType()
    {
        if (is_null($this->_contentType)) {
            $type = $this->_getType();
            if ($type == 'original') {
                $file = $this->_getFile();
                $this->_contentType = $file->mime_type;
            }
            else {
               $this->_contentType = 'image/jpeg';
            }
        }

        return $this->_contentType;
    }

    /**
     * Get and set sending mode (always inline).
     *
     * @return string Disposition 'inline' (default) or 'attachment'.
     */
    protected function _getMode()
    {
        if (is_null($this->_mode)) {
            $this->_mode = 'inline';
        }

        return $this->_mode;
    }

    /**
     * Get and set theme via referrer (public if unknow or unidentified user).
     *
     * @return string "public" or "admin".
     */
    protected function _getTheme()
    {
        if (is_null($this->_theme)) {
            // Default is set to public.
            $this->_theme = 'public';
            // This allows quick control if referrer is not set.
            if (current_user()) {
                $referrer = (string) $this->getRequest()->getServer('HTTP_REFERER');
                if (strpos($referrer, WEB_ROOT . '/admin/') === 0) {
                    $this->_theme = 'admin';
                }
            }
        }

        return $this->_theme;
    }

    /**
     * Redirect to previous page.
     */
    protected function _gotoPreviousPage()
    {
        $referrer = $this->getRequest()->getServer('HTTP_REFERER');
        if ($referrer) {
            $this->redirect($referrer);
        }
        else {
            $this->redirect(WEB_ROOT);
        }
    }
}
