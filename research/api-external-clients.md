# API für externe Clients (Plasmai u. a.)

Raised 2026-09-23. Vorarbeit für eine REST-API, die dem Plugin externe Clients erlaubt, ihre eigene
Oberfläche danach auszurichten, ob ein Zeiteintrag/Tag ein Filmtag ist oder nicht — ohne die
Drehzettel-Logik (Engagement, Ruleset, TV FFS) selbst nachbauen zu müssen. Auslöser: Plasmai
(`~/Hacking/eigene/Plasmai`), ein KDE-Plasma-6-Panel-Widget für Zeiterfassung mit Kimai als einem von
vier Backends, soll die Filmtag-Felder (Pause, Catering, Kategorie, Notiz) im eigenen Bearbeiten-Dialog
anzeigen können, wenn Projekt/User/Datum ein aktives Engagement haben.

## Wie Plasmai heute mit Kimai spricht

Geprüft in `contents/code/kimaiApi.js`: Plasmai ruft Kimais Standard-REST-API direkt auf, gegen
dieselbe Basis-URL wie die Kimai-Installation, mit `Authorization: Bearer <API-Token>` (derselbe
Token, den der Nutzer für den Kimai-Login/API-Zugriff anlegt). Verbindungstest läuft über
`GET /api/version`. Das ist das Muster, an das sich neue Plugin-Endpunkte anschließen sollten — kein
separates Auth-System, keine eigene Basis-URL, einfach `/api/drehzettel/...` auf demselben Host.

## Geplante Endpunkte (Entwurf, nicht implementiert)

Alle unter `/api/drehzettel/...`, authentifiziert wie Kimais eigene API (derselbe Firewall-Bereich,
kein Zusatzaufwand für den Client).

- **`GET /api/drehzettel/ping`** — Discovery. Liefert `{installed: true, version: "x.y.z"}`. Zweck:
  externe Clients müssen vor jedem anderen Aufruf wissen, ob das Plugin auf dieser Kimai-Instanz
  überhaupt installiert ist (das Plugin ist laut `roadmap.md` explizit optional/inert ohne
  Engagement — und schlicht nicht vorhanden auf Installationen ohne das Bundle). Ein 404 hier heißt
  "nicht installiert"; Ergebnis sollte clientseitig pro Instanz gecacht werden.

- **`GET /api/drehzettel/engagement-status?project=&user=&date=`** — Die zentrale Abfrage für
  UI-Entscheidungen. `user` optional, Default: Token-Inhaber. `date` optional, Default: heute.
  Antwort: `{active: bool, engagementId, toggleDefault: bool, rulesetName}`. Ein externer Client ruft
  das auf, sobald Projekt/Datum in seinem eigenen Formular gewählt sind, um zu entscheiden, ob er
  seine eigenen Filmtag-Felder überhaupt anzeigt — genau die Funktion, die auch die Kimai-eigene
  Formular-Extension nutzt (`EngagementService::active(user, project, date)` existiert bereits
  serverseitig, siehe `research/ux-flows-film-day-data.md`).

- **`GET /api/drehzettel/film-days/{date}?project=&user=`** — liest die `FilmDay`-Felder (Pause,
  Catering, Kategorie, Tagestyp, Notiz) für den Tag, falls ein Datensatz existiert, sonst die
  Regelwerk-Defaults (spiegelt `DayInputBuilder` nach außen).

- **`PUT /api/drehzettel/film-days/{date}?project=&user=`** — Upsert derselben Felder. Nötig, falls
  ein externer Client (z. B. Plasmai) die Filmtag-Felder direkt bearbeiten lassen will, nicht nur
  anzeigen.

Zwei Endpunkte (Status-Check + Read/Write der Felder) reichen aus; keine weiteren Spezialrouten nötig
— alles andere (Wochensummen, Zuschläge, PDF) bleibt bewusst nur in der Kimai-Weboberfläche des
Plugins, das sind keine Daten, die ein externer Client zum Anpassen seiner eigenen UI braucht.

## Kimai-2.67.0-API-Unterbau — verifiziert am Quellcode

Geprüft direkt im offiziellen Image `kimai/kimai2:2.67.0` (Docker, read-only, `docker run --rm
--entrypoint cat ... /opt/kimai/src/API/...` bzw. `/opt/kimai/config/...`), nicht nur aus der
Dokumentation:

- **Stack**: FOSRestBundle + `symfony/routing`-Attribute + JMS Serializer + `nelmio/api-doc-bundle`
  für die OpenAPI-Doku. Kimai hat seinen eigenen API-Code nicht auf API Platform umgestellt.
- **Routing**: `config/routes.yaml` importiert `src/API/` mit `prefix: /api` als eigene Route-Quelle
  (`type: attribute`). Jeder Controller trägt selbst nur `#[Route(path: '/timesheets')]` usw. ohne
  `/api`-Präfix — das Präfix kommt ausschließlich aus dem `prefix: /api` der Route-Importzeile.
- **Unser Plugin importiert bereits eine eigene `Resources/config/routes.yaml`** (aktuell mit
  `prefix: /{_locale}` für `Controller/`). Eine zweite Resource-Zeile mit eigenem Verzeichnis
  (z. B. `Controller/Api/`) und `prefix: /api/drehzettel` reicht, um eigene API-Routen zu
  registrieren — kein Eingriff in Kimai-Core-Config nötig, folgt exakt dem bestehenden Muster.
- **Auth ist pfadunabhängig wiederverwendbar**: `#[IsGranted('API')]` prüft über
  `App\Voter\ApiVoter` (global registrierter Security-Voter), ob der Request per API-Token
  authentifiziert ist und die Rolle `api_access` hat — unabhängig davon, welcher Controller das
  Attribut trägt. Unsere Endpunkte können also exakt denselben Bearer-Token-Mechanismus nutzen, den
  Plasmai schon verwendet, ohne eigene Auth-Logik.
- **OpenAPI-Doku ist pfadbasiert, nicht verzeichnisbasiert**: `nelmio_api_doc.areas.default.
  path_patterns: ['^/api(?!/doc)']` — Nelmio scannt jede Route, deren Pfad mit `/api/` beginnt
  (außer `/api/doc` selbst), unabhängig davon, aus welchem Bundle/Verzeichnis sie kommt. Unsere
  `/api/drehzettel/...`-Routen tauchen also **automatisch** unter `/api/doc` neben den Kimai-eigenen
  Endpunkten auf, sobald sie mit `OA\Attributes` dokumentiert sind — keine Zusatzkonfiguration nötig.
- **FOSRest-Verhalten ebenfalls pfadbasiert**: `fos_rest.format_listener.rules` und `fos_rest.zone`
  greifen für `^/api` bzw. `^/api/*` — JSON-Format-Negotiation, Exception-Mapping (z. B.
  `ValidationFailedException` → 400) gelten für unsere Routen automatisch mit, ohne dass wir das
  `App\API\ViewHandler` oder die FOSRest-Konfiguration selbst anfassen müssen.

Fazit: der ursprünglich als offen markierte Punkt ist geklärt — Plugin-API-Controller unter
`/api/drehzettel/...` bekommen Auth, Doku-Sichtbarkeit und JSON-Handling geschenkt, rein durch die
Wahl des Pfad-Präfixes und die Wiederverwendung von `#[IsGranted('API')]`.

## Versionierung

Kimai selbst versioniert seine API nicht im URL-Pfad (nur ein Freitext-Feld `version: '1.1'` in den
OpenAPI-Metadaten, der Pfad bleibt `/api/...`). Für unsere Plugin-API — die von einem externen Client
wie Plasmai unabhängig vom Kimai-Release-Zyklus konsumiert wird — reicht das nicht: Plasmai kann nicht
wissen, ob sich die Antwortform von `engagement-status` zwischen zwei Plugin-Versionen geändert hat,
wenn der Pfad gleich bleibt.

**Entschieden**: URL-Pfad-Versionierung, `/api/drehzettel/v1/...`. Eine neue Major-Version bekommt
einen neuen Pfad-Präfix (`v2`), alte Clients laufen unverändert gegen `v1` weiter, solange die
Route besteht. Vorteil gegenüber einem reinen `apiVersion`-Feld in der Ping-Antwort: v1 und v2 können
parallel laufen, während ein Client migriert — mit einem Antwortfeld müsste der Client stattdessen
sein Verhalten zur Laufzeit basierend auf der gemeldeten Version verzweigen, was mehr Aufwand auf der
Client-Seite bedeutet. Der `ping`-Endpoint bleibt zusätzlich sinnvoll (Discovery, ob das Plugin
überhaupt installiert ist), meldet aber nur noch Plugin-Version und unterstützte API-Major-Versionen,
nicht die Antwortform selbst.

Endpunkte damit final:

- `GET /api/drehzettel/ping` — `{installed: true, pluginVersion: "x.y.z", apiVersions: ["v1"]}`
- `GET /api/drehzettel/v1/engagement-status?project=&user=&date=`
- `GET /api/drehzettel/v1/film-days/{date}?project=&user=`
- `PUT /api/drehzettel/v1/film-days/{date}?project=&user=`

## Offene technische Fragen (vor Implementierung zu klären)

- **Berechtigungen**: welche Kimai-Rolle/Permission darf `film-days` eines *anderen* Users lesen
  (Produktionsleitung vs. Crew-Mitglied selbst) — an Kimais bestehendes Permission-System
  (`drehzettel`, `drehzettel_manage`) anlehnen, nicht neu erfinden.
- **JMS-Serializer-Gruppen für die neuen Endpunkte**: Kimai nutzt für seine eigenen Entities
  Serializer-Groups (`Default`, `Entity`, `Timesheet_Entity`, …) und registriert sie in
  `nelmio_api_doc.yaml` unter `models.names`. Für `FilmDay`-Antworten muss geklärt werden, ob wir
  dasselbe Muster übernehmen (eigene Groups + Alias-Eintrag) oder mit einem einfacheren
  Response-DTO ohne JMS-Gruppen auskommen — Letzteres vermutlich ausreichend, da `FilmDay` deutlich
  kleiner als Kimais Entities ist und keine Expand/Collapse-Varianten braucht.

## Plasmai-Integration (nach API-Umsetzung)

Plasmai modelliert Backends bereits austauschbar (Kimai/Clockify/Toggl/SolidTime). Drehzettel ist kein
eigenes Backend, sondern eine optionale *Erweiterung* on top of eines Kimai-Profils: nach
`GET /api/drehzettel/ping` (einmal pro Kimai-Profil, gecacht, liefert auch `apiVersions` — Plasmai
prüft, ob `v1` dabei ist, bevor es die `v1`-Endpunkte anspricht) weiß Plasmai, ob es die Filmtag-Felder
im Bearbeiten-Dialog überhaupt anbieten kann. Bei aktivem Engagement (`engagement-status`) blendet es
Pause (TV FFS)/Catering/Kategorie/Notiz zusätzlich zu den bestehenden Kimai-Feldern ein — ähnlich zu
Konzept A/B aus dem Workflow-Artefact, aber im Kontext von Plasmais eigenem kompakten Popup vermutlich
eher ein Drawer/Sheet (Konzept B) als ein Tab-Wechsel, da der Dialog dort schon minimal ist.

## Nächste Schritte

- Kimai-API-Unterbau am Code verifizieren (nicht nur aus der Dokumentation), bevor Controller gebaut
  werden.
- Die zwei Endpunkte (`engagement-status`, `film-days`) im Plugin implementieren, inkl. `ping`.
- Danach: Plasmai-seitige Erweiterung (separates Projekt, eigener Implementierungsschritt).
