# Changelog

## 0.3.0

- Mail log: the dialog "Mail template and automatic sending" lists the last 10 mails of the engagement, automatic and manual, with time, period, recipient and result (sent, failed, skipped). A retry after a failure counts on the same row; only administrators see the reason of a failure. Needs `kimai:bundle:drehzettel:install` (new table).
- Kimai's working times page (`/contract`) marks every shooting day the Drehzettel counts, also days whose entries came from the stopwatch or quick entry and have no saved film day; travel days are not marked.
- Automatic mails that were sent or skipped are written to the Kimai log.
- PHP 8.1: no enum cases in property defaults.
- Release zip built and attached by GitHub Actions.
