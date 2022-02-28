Statistics (module for Omeka S)
===============================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Statistics] is a module for [Omeka S] that counts views of pages in order to
know the least popular resource and the most viewed pages. It provides useful
infos on visitors too (language, referrer...). So this is an analytics tool like
[Matomo] (open source), [Google Analytics] (proprietary, no privacy) and other
hundreds of such [web loggers].

It has some advantages over them:
- simple to manage (a normal module, with same interface);
- adapted (statistics can be done by resource and not only by page);
- integrated, so statistics can be displayed on any page easily;
- informative (query, referrer, user agent and language are saved; all
  statistics can be browsed by public);
- count of direct download of files;
- full control of pruducted data;
- respect of privacy by default.

On the other hand, some advanced features are not implemented, especially
a detailled board with advanced filters. Nevertheless, logs and data can be
exported via mysql to a spreadsheet like [LibreOffice] or another specialized
statistic tool, where any statistics can be calculated.

Of course, you must respect privacy of users and visitors.

This module is a direct upgrade of the plugin [Statistics for Omeka Classic].


Installation
------------

### Module

This module can use the optional module [Generic].

* From the zip

Download the last release [Statistics.zip] from the list of releases, and
uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Statistics`.

Then install it like any other Omeka module and follow the config instructions.

See general end user documentation for [installing a module].

### Count downloads of files

If you don't use apache logs or another specialized tool ot count files
download, you can enable it in the module and to config the file `.htaccess` at
the root of the server.

Just add a line in the beginning off `.htaccess`, after `RewriteEngine on`:

```htaccess
RewriteRule ^files/original/(.*)$ https://example.org/download/files/original/$1 [NC,L]
```

If you use the anti-hotlinking feature of [Archive Repertory] to avoid bandwidth
theft, you should keep its rule. Statistics for direct downloads of files will be
automatically added.

You can count large files too, but this is not recommended, because in the
majority of themes, hits may increase even when a simple page is opened.


Usage
-----

### Browse statistics

A summary of statistics is displayed at `/statistics/summary`.

Lists of statistics by page, by resource or by field are available too. They can
be ordered and filtered by anonymous / identified users, resource types, etc.

These pages can be made available to authorized users only or to all public.

### Displaying some statistics in the theme

Statistics of a page or resource can be displayed on any page via three mechanisms.

#### Events

An option allows to append the statistics automatically on some resource `show`
and `browse` pages via the events. Just enable them through the module [Blocks Disposition].

#### Helper "statistic"

The helpers `statistic` can be used for more flexibility:

```php
echo $this->statistic()->positionResource($resource);
echo $this->statistic()->textPage($currentUrl);
```

#### Shortcodes

Shortcode are available through the module [Shortcode].

Some illustrative examples:

```
[stats]
[stats_total url="/login"]
[stats_total resource="items"]
[stats_total resource="items" id=1]
[stats_position]
[stats_position url="/item/search"]
[stats_position resource="item_sets" id=1]
[stats_vieweds]
[stats_vieweds type="none"]
[stats_vieweds order="last" type="resource"]
[stats_vieweds order="most" type="download" number=1]
```

Arguments for Omeka Classic were adapted for Omeka S.

All arguments are optional. Arguments are:
* For `stats_total` and `stats_position`
  - `type`: If "download", group all downloaded files linked to the specified
  resource (all files of an item, or all files of all items of a).
  Else, the type is automatically detected ("resource" if a resource is set,
  "page" if an url is set or if nothing is set).
  - `resource` (Omeka classic: `record_type`): one or multiple Omeka resource
  type, e.g. "items" or "item_sets", or "media". By default, a viewed resource
  is counted for each hit on the dedicated page of a resource, like "/item/xxx".
  Alternatively, the url can be used (with the argument `url`, but to count the
  downloaded files, this is an obfuscated one except if [Archive Repertory] is
  used.
  - `id` (Omeka classic: `record_id`): the identifier of the resource (not the
  slug if any). It implies one specific `resource` and only one. With
  `stats_position`, `id` is required when searching by resource.
  - `url`: the url of a specific page. A full url is not needed; a partial Omeka
  url without web root is enough (in any case, web root is removed
  automatically). This argument is used too to know the total or the position of
  a file. This argument is not used if `resource` argument is set.

* For `stats_vieweds`
  - `type`: If "page" or "download", most or last viewed pages or downloaded
  files will be returned. If empty or "all", it returns only pages with a
  dedicated resource. If "none", it returns pages without dedicated resource. If
  one or multiple Omeka resource type, e.g. "items" or "item_sets", most or last
  resources of this resource type will be returned.
  - `sort`: can be "most" (default) or "last".
  - `number`: number of resources to return (10 by default).
  - `offset`: offset to set page to return.

The event and the helper return the partial from the theme.

`stats_total` and `stats_position` return a simple number, surrounded by a
`span` tag when shortcode is used.
`stats_vieweds` returns an html string that can be themed.


Notes
-----

- Hits of anonymous users and identified users are counted separately.
- Only pages of the public theme are counted.
- Reload of a page generates a new hit (no check).
- IP can be hashed (default) or truncated for privacy purpose.
- Currently, screen size is not detected.


TODO
----

- [ ] Fix and finalize statistics for public side and shortcodes.
- [ ] Statistics for api.
- [ ] Add summary in public side.
- [ ] Move some options to site settings.
- [ ] Store the site id.
- [ ] Add stats by site.
- [ ] Check CleanUrl.
- [ ] Merge the stats page/download and resource.
- [ ] Improve rights to read/create or filter visitors data on api.
- [ ] Move all statistics methods from Stat and Hit models to Statistic Helper?
- [ ] Improve stats by item sets.
- [ ] Add tests.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2014-2022 (see [Daniel-KM] on GitLab)


[Statistics]: https://gitlab.com/Daniel-KM/Omeka-S-module-Statistics
[Omeka S]: https://omeka.org/s
[Matamo]: https://matomo.org
[Google Analytics]: https://www.google.com/analytics
[web loggers]: https://en.wikipedia.org/wiki/List_of_web_analytics_software
[LibreOffice]: https://www.documentfoundation.org
[Statistics for Omeka Classic]: https://gitlab.com/Daniel-KM/Omeka-plugin-Stats
[Generic]: https://gitlab.com/Daniel-KM/Omeka-S-module-Generic
[Shortcode]: https://gitlab.com/Daniel-KM/Omeka-S-module-Shortocode
[Archive Repertory]: https://gitlab.com/Daniel-KM/Omeka-S-module-ArchiveRepertory
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Statistics/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: https://github.com/sandywalker/webui-popover/blob/master/LICENSE.txt
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
