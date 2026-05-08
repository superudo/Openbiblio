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
