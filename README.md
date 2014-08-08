Stats (plugin for Omeka)
========================

About
-----

[Stats] is a plugin for [Omeka] that counts views of pages in order to know the
least popular record and the most viewed pages. It provides useful infos on
visitors too (language, referrer...). So this is an analytics tool like [Piwik]
(open source), [Google Analytics] (proprietary, no privacy) and other hundreds
of such web loggers.

It has some advantages over them:
- simple to manage (a normal plugin, with same interface);
- adapted (stats can be done by record and not only by page);
- integrated, so stats can be displayed on any page easily;
- extensible (for plugins makers, the filter `stats_record` allows other
plugins to get stats for a specific record type);
- informative (query, referrer, user agent and language are saved; all stats can
be browsed by public);
- full control of pruducted data;
- respect of privacy (if wanted!).

On the other hand, some advanced features are not implemented, especially
a detailled board with advanced filters. Nevertheless, logs and data can be
exported via mysql to a spreadsheet like [LibreOffice] or another specialized
statistic tool, where any stats can be calculated.


Installation
------------

Uncompress files and rename plugin folder "Stats".

Then install it like any other Omeka plugin and follow the config instructions.


Browse Stats
------------

A summary of stats is displayed at `/stats/summary`.

Lists of stats by page, by record or by field are available too. They can be
ordered and filtered by anonymous / identified users, record types, etc.

These pages can be made available to authorized users only or to all public.

For plugins makers, panels can be added via the hook `stats_summary`.


Displaying some stats in the theme
----------------------------------

Stats of a page or record can be displayed on any page via three mechanisms.

* Hooks

An option allows to append the stats automatically on some records `show` and
`browse` pages via the hooks:

```php
fire_plugin_hook('public_items_show', array('view' => $this, 'item' => $item));
```

* Helpers

Helpers can be used for more flexibility:

```php
echo $this->stats()->position_record($record);
echo $this->stats()->text_page(current_url());
```

* Shortocodes

[Shortcodes] are supported (Omeka 2.2 or above). Some illustrative examples:

```
[stats_total]
[stats_total url="/"]
[stats_total record_type="Item"]
[stats_total record_type="Item" record_id=1]
[stats_position]
[stats_position url="/items/search"]
[stats_position record_type="Collection" record_id=1]
[stats_vieweds]
[stats_vieweds record_type="none"]
[stats_vieweds order="last" record_type="Item"]
[stats_vieweds order="most" record_type="Collection" number=1]
```

All arguments are optional. Arguments are:
* For `stats_total` and `stats_position`
  - `record_type`: one or multiple Omeka record type, e.g. `Item` or
  `Collection`.
  - `record_id`: the identifier of the record (not the slug if any). It implies
  one specific `record_type` and only one. With `stats_position`, `record_id` is
  required when searching by record.
  - `url`: the url of a specific page. A full url is not needed; a partial Omeka
  url without web root is enough (in any case, web root is removed
  automatically). This argument is not used if `record_type` argument is set.

* For `stats_vieweds`
  - `record_type`: one or multiple Omeka record type, e.g. `Item` or
  `Collection`. If empty or "all", it returns only pages with a dedicated record.
  If "none", it returns pages without dedicated record.
  - `sort`: can be "most" (default) or "last".
  - `number`: number of records to return (10 by default).
  - `offset`: offset to set page to return.

The hook and the helper return the partial from the theme.

`stats_total` and `stats_position` return a simple number surrounded by a
`span` tag.
`stats_vieweds` returns an html string that can be themed.


Notes
-----

- Hits of anonymous users and identified users are counted separately.
- Only pages of the public theme are counted.
- Reload of a page generates a new hit (no check).
- IP can be hashed or not saved for privacy.
- Currently, direct downloads of original files are not counted.
- Currently, screen sizes is not detected.


Warning
-------

Use it at your own risk.

It's always recommended to backup your files and database regularly so you can
roll back if needed.


Troubleshooting
---------------

See online issues on the [Stats issues] page on GitHub.


License
-------

This plugin is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software's author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user's
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.


Contact
-------

Current maintainers:
* Daniel Berthereau (see [Daniel-KM] on GitHub)


Copyright
---------

* Copyright Daniel Berthereau, 2014


[Omeka]: https://omeka.org "Omeka.org"
[Stats]: https://github.com/Daniel-KM/Stats
[Piwik]: https://piwik.org
[Google Analytics]: http://www.google.com/analytics
[LibreOffice]: https://www.documentfoundation.org
[Shortcodes]: http://omeka.org/codex/Shortcodes
[Stats issues]: https://github.com/Daniel-KM/Stat/issues
[CeCILL v2.1]: http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html "GNU/GPL v3"
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
