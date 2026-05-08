# Sequentielle Migrationen: Lösung des Versionskonflikts-Problems

## Das aktuelle Problem

**Derzeit erfordert jede Datenbankänderung eine Erhöhung der Versionsnummer.**

Dies führt zu einem schmerzhaften Workflow:

### Szenario 1: Zwei Feature-Branches
- Branch A fügt einen neuen Materialtyp hinzu → benötigt Version 0.8.2
- Branch B fügt Datenbankindizes hinzu → benötigt ebenfalls Version 0.8.2
- **Problem**: Beide Branches ändern dieselbe Version. Merge-Konflikt!
- **Aktuelle Lösung**: Ein Branch wartet, dann Neunummerierung auf 0.8.3
- **Schmerz**: Datenbankänderungen können nicht parallel entwickelt werden

### Szenario 2: Hotfix während der Entwicklung
- Version 0.9.0 ist in Entwicklung mit Schema-Änderungen
- Kritischer Bug in 0.8.1 gefunden, der eine Schema-Korrektur benötigt
- **Problem**: Kann 0.8.2 Hotfix nicht veröffentlichen, weil 0.9.0 bereits existiert
- **Aktuelle Lösung**: Entweder Hotfix überspringen oder komplexes Versions-Jonglieren
- **Schmerz**: Keine ordentlichen Hotfix-Releases möglich

### Szenario 3: Pre-Release-Testing
- Sie möchten Schema-Änderungen in einem Feature-Branch testen
- Aber Sie können noch nicht auf 0.8.2 erhöhen (nicht bereit für Release)
- **Problem**: Datenbankänderungen können nicht ohne Versionskonflikte getestet werden
- **Aktuelle Lösung**: Temporäre Versionsnummern verwenden, später neu nummerieren
- **Schmerz**: Zusätzliche Arbeit, Fehlerrisiko

## Die Lösung: Schema von Version entkoppeln

**Statt:** "Version 0.8.2 enthält diese Schema-Änderungen"  
**Verwende:** "Migration 015 fügt Indizes hinzu, Migration 016 fügt Materialtypen hinzu"

### Wie es funktioniert

1. **Schema-Änderungen erhalten sequentielle Nummern** (001, 002, 003...)
2. **Anwendungsversion bleibt separat** (weiterhin 0.8.1, 0.8.2, etc.)
3. **Datenbank verfolgt, welche Migrationen ausgeführt wurden** (nicht welche Version sie hat)

### Gleiches Szenario, neues System

**Szenario 1: Zwei Feature-Branches**
- Branch A: Erstellt `migrations/015_add_material_types.php`
- Branch B: Erstellt `migrations/016_add_indexes.php`
- **Kein Konflikt!** Unterschiedliche Dateinamen
- Falls beide 015 verwenden, ist der Merge-Konflikt offensichtlich und leicht zu beheben (einen auf 017 umnummerieren)

**Szenario 2: Hotfix während der Entwicklung**
- 0.9.0 Entwicklung hat Migrationen 015-020 (noch nicht released)
- Hotfix für 0.8.1 benötigt eine Schema-Änderung
- **Lösung**: Hotfix erstellt Migration 015 im Hotfix-Branch
- 0.8.2 Hotfix wird mit Migration 015 ausgeliefert
- 0.9.0 Development-Branch muss seine Migrationen auf 016-021 umnummerieren
- **Vorteil**: Hotfix ist möglich, Umnummerierung ist nur im noch nicht released Branch nötig

**Szenario 3: Pre-Release-Testing**
- Feature-Branch erstellt Migration 015
- Sofort testen, keine Versionserhöhung nötig
- **Kein Konflikt!** Migrationsnummern kollidieren nicht mit Versionen

## Was ändert sich für Sie?

### Alter Workflow
```
1. Schema-Änderung im Feature-Branch machen
2. SQL zur Upgrade-Funktion hinzufügen
3. Versionsnummer erhöhen
4. Hoffen, dass kein anderer Branch dieselbe Version erhöht hat
5. Merge-Konflikte wenn doch
6. Alle Änderungen vor Release sammeln
```

### Neuer Workflow
```
1. Schema-Änderung im Feature-Branch machen
2. Migrationsdatei erstellen: migrations/NNN_description.php
3. Keine Versionserhöhung nötig
4. Merge - keine Konflikte (verschiedene Dateien)
5. Migrationen laufen automatisch beim Upgrade
```

## Was bleibt gleich?

✅ **Release-Prozess**: Weiterhin Release 0.8.2, 0.9.0, etc.  
✅ **Upgrade-Assistent**: Benötigt weiterhin Upgrade-Key  
✅ **Benutzererfahrung**: Benutzer klicken weiterhin auf "Upgrade"-Button  
✅ **Sicherheit**: Weiterhin nicht-destruktiv, weiterhin Backup erforderlich  
✅ **Docker**: Weiterhin manuelles Upgrade (keine Auto-Migration)  

## Was wird besser?

✅ **Parallele Entwicklung**: Mehrere Branches können Schema-Änderungen haben  
✅ **Hotfixes**: Kann 0.8.2 veröffentlichen, auch wenn 0.9.0 existiert  
✅ **Testing**: Kann Schema-Änderungen vor Release testen  
✅ **Historie**: Klare Aufzeichnung, was wann geändert wurde  
✅ **Konflikte**: Einfacher zu erkennen und zu lösen  

## Reales Beispiel: Die Index-Migration

**Alter Weg:**
- Warten bis kurz vor 0.8.2 Release
- Alle Indizes zur Upgrade-Funktion hinzufügen
- Version auf 0.8.2 erhöhen
- Alles zusammen ausliefern

**Neuer Weg:**
- `migrations/015_add_indexes.php` jetzt erstellen
- Im Feature-Branch testen
- Mergen wenn bereit
- 0.8.2 Release enthält Migration 015 (und alle anderen)
- Benutzer upgraden, Migration 015 läuft automatisch

## Für bestehende 0.8.1 Installationen

**Keine Breaking Changes!**

Wenn ein 0.8.1 Benutzer upgradet:
1. System erkennt fehlende Migrationstabelle
2. Erstellt Migrationstabelle
3. Markiert Migrationen 001-006 als "bereits angewendet" (repräsentiert 0.8.1)
4. Führt neue Migrationen aus (015+)
5. Fertig!

Sie sehen keinen Unterschied außer besseren Upgrade-Meldungen.

## Das Fazit

**Dies ändert nicht Ihre Release-Strategie.**

Sie können weiterhin:
- Änderungen vor Release sammeln
- Veröffentlichen wenn bereit
- Kontrollieren was in jede Version kommt

**Es entfernt nur den Versionsnummer-Flaschenhals**, sodass mehrere Personen an Schema-Änderungen arbeiten können ohne Konflikte.

## Fragen?

**F: Müssen wir ändern, wie wir releasen?**  
A: Nein. Sie entscheiden weiterhin, wann 0.8.2, 0.9.0, etc. veröffentlicht wird.

**F: Können wir weiterhin Änderungen bündeln?**  
A: Ja. Migrationen laufen nur, wenn Benutzer auf "Upgrade" klicken.

**F: Was wenn zwei Branches dieselbe Migrationsnummer verwenden?**  
A: Zwei Fälle:
- Gleicher Name (`015_add_indexes.php`): Git zeigt Merge-Konflikt → leicht zu erkennen
- Gleiche Nummer, anderer Name (`015_add_indexes.php` vs `015_add_material.php`): Git merged beide Dateien → **Sequenz-Lücken-Check erkennt das Problem**. Der Merge-Gate-Prozess sollte die Testseite ausführen, die warnt wenn Lücken oder Duplikate existieren. Einer muss dann auf 016 umnummeriert werden.

**F: Ist das ein großes Rewrite?**  
A: Nein. ~1.100 Zeilen neuer Code. Bestehende Upgrade-Funktionen bleiben bis wir sie konvertieren.

**F: Können wir das testen?**  
A: Ja! Funktionierende Demo verfügbar. Bootstrap getestet, Migration 015 getestet, alles funktioniert.

**F: Was ist das Risiko?**  
A: Gering. Vollständig rückwärtskompatibel. Bestehende 0.8.1 Installationen upgraden nahtlos.

## Ausprobieren

Der funktionierende Code ist im Branch `feature/database-migration`:
- Testseiten zeigen, dass es funktioniert
- Bootstrap-Prozess getestet
- Migration 015 (Indizes) erfolgreich angewendet
- Kein Datenverlust, keine Breaking Changes

Siehe `README-MIGRATION.md` für vollständige Details und Testergebnisse.

---

# Sequential Migrations: Solving the Version Conflict Problem

## The Current Problem

**Right now, every database change requires bumping the version number.**

This creates a painful workflow:

### Scenario 1: Two Feature Branches
- Branch A adds a new material type → needs version bump to 0.8.2
- Branch B adds database indexes → also needs version bump to 0.8.2
- **Problem**: Both branches modify the same version. Merge conflict!
- **Current solution**: One branch waits, then renumbers to 0.8.3
- **Pain**: Can't work on database changes in parallel

### Scenario 2: Hotfix During Development
- Version 0.9.0 is in development with schema changes
- Critical bug found in 0.8.1 that needs a schema fix
- **Problem**: Can't release 0.8.2 hotfix because 0.9.0 already exists
- **Current solution**: Either skip the hotfix or do complex version juggling
- **Pain**: Can't do proper hotfix releases

### Scenario 3: Pre-release Testing
- You want to test schema changes in a feature branch
- But you can't bump to 0.8.2 yet (not ready for release)
- **Problem**: Can't test database changes without version conflicts
- **Current solution**: Use temporary version numbers, renumber later
- **Pain**: Extra work, risk of mistakes

## The Solution: Decouple Schema from Version

**Instead of:** "Version 0.8.2 contains these schema changes"  
**Use:** "Migration 015 adds indexes, Migration 016 adds material types"

### How It Works

1. **Schema changes get sequential numbers** (001, 002, 003...)
2. **Application version stays separate** (still 0.8.1, 0.8.2, etc.)
3. **Database tracks which migrations ran** (not which version it is)

### Same Scenario, New System

**Scenario 1: Two Feature Branches**
- Branch A: Creates `migrations/015_add_material_types.php`
- Branch B: Creates `migrations/016_add_indexes.php`
- **No conflict!** Different file names
- If both use 015, merge conflict is obvious and easy to fix (renumber one to 017)

**Scenario 2: Hotfix During Development**
- 0.9.0 development has migrations 015-020 (not yet released)
- Hotfix for 0.8.1 needs a schema change
- **Solution**: Hotfix creates migration 015 in hotfix branch
- 0.8.2 hotfix ships with migration 015
- 0.9.0 development branch must renumber its migrations to 016-021
- **Advantage**: Hotfix is possible, renumbering only needed in unreleased branch

**Scenario 3: Pre-release Testing**
- Feature branch creates migration 015
- Test it immediately, no version bump needed
- **No conflict!** Migration numbers don't collide with versions

## What Changes for You?

### Old Workflow
```
1. Make schema change in feature branch
2. Add SQL to upgrade function
3. Bump version number
4. Hope no other branch bumped the same version
5. Merge conflicts if they did
6. Collect all changes before release
```

### New Workflow
```
1. Make schema change in feature branch
2. Create migration file: migrations/NNN_description.php
3. No version bump needed
4. Merge - no conflicts (different files)
5. Migrations run automatically on upgrade
```

## What Stays the Same?

✅ **Release process**: Still release 0.8.2, 0.9.0, etc.  
✅ **Upgrade wizard**: Still requires upgrade key  
✅ **User experience**: Users still click "Upgrade" button  
✅ **Safety**: Still non-destructive, still requires backup  
✅ **Docker**: Still manual upgrade (no auto-migration)  

## What Gets Better?

✅ **Parallel development**: Multiple branches can have schema changes  
✅ **Hotfixes**: Can release 0.8.2 even if 0.9.0 exists  
✅ **Testing**: Can test schema changes before release  
✅ **History**: Clear record of what changed when  
✅ **Conflicts**: Easier to detect and resolve  

## Real Example: The Index Migration

**Old way:**
- Wait until just before 0.8.2 release
- Add all indexes to upgrade function
- Bump version to 0.8.2
- Ship everything together

**New way:**
- Create `migrations/015_add_indexes.php` now
- Test it in feature branch
- Merge whenever ready
- 0.8.2 release includes migration 015 (and any others)
- Users upgrade, migration 015 runs automatically

## For Existing 0.8.1 Installations

**No breaking changes!**

When a 0.8.1 user upgrades:
1. System detects no migration table
2. Creates migration table
3. Marks migrations 001-006 as "already applied" (representing 0.8.1)
4. Runs any new migrations (015+)
5. Done!

They don't see any difference except better upgrade messages.

## The Bottom Line

**This doesn't change your release strategy.**

You can still:
- Collect changes before release
- Release when ready
- Control what goes into each version

**It just removes the version number bottleneck** so multiple people can work on schema changes without conflicts.

## Questions?

**Q: Do we have to change how we release?**  
A: No. You still decide when to release 0.8.2, 0.9.0, etc.

**Q: Can we still batch changes?**  
A: Yes. Migrations only run when users click "Upgrade."

**Q: What if two branches use the same migration number?**  
A: Two cases:
- Same name (`015_add_indexes.php`): Git shows merge conflict → easy to detect
- Same number, different name (`015_add_indexes.php` vs `015_add_material.php`): Git merges both files → **Sequence gap check detects the problem**. The merge gate process should run the test page, which warns if gaps or duplicates exist. One must then be renumbered to 016.

**Q: Is this a big rewrite?**  
A: No. ~1,100 lines of new code. Existing upgrade functions stay until we convert them.

**Q: Can we test this?**  
A: Yes! Working demo available. Bootstrap tested, migration 015 tested, all working.

**Q: What's the risk?**  
A: Low. Fully backward compatible. Existing 0.8.1 installations upgrade seamlessly.

## Try It

The working code is in branch `feature/database-migration`:
- Test pages show it working
- Bootstrap process tested
- Migration 015 (indexes) successfully applied
- No data loss, no breaking changes

See `README-MIGRATION.md` for full details and test results.
