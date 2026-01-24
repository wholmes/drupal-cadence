# Git Workflow Guide

This document explains how to work on new features while always being able to revert to the stable working version.

## Current Setup

- **`main` branch**: Contains the stable working version (committed)
- **`stable-working` branch**: Backup branch pointing to the same commit
- **`v1.0-working` tag**: Tagged version for easy reference
- **`development` branch**: Active branch for new features (currently checked out)

## Quick Reference

### See Current Status
```bash
git status                    # What files have changed
git branch                    # Which branch you're on
git log --oneline -5          # Recent commits
```

### Switch to Working Version
```bash
git checkout main             # Switch to stable version
# or
git checkout stable-working   # Same thing, different branch name
# or
git checkout v1.0-working    # Checkout by tag
```

### Start Working on New Features
```bash
git checkout development      # Switch to development branch
# Make your changes...
git add .
git commit -m "Description of changes"
```

### Revert to Working Version
```bash
# Option 1: Discard all changes and go back
git checkout main
git branch -D development     # Delete development branch
git checkout -b development   # Create fresh development branch

# Option 2: Keep changes but switch branches
git stash                     # Save changes temporarily
git checkout main             # Switch to stable
git checkout development      # Switch back
git stash pop                 # Restore changes

# Option 3: Reset development branch to match main
git checkout development
git reset --hard main         # WARNING: This discards all changes in development
```

### Create New Checkpoint
```bash
# After testing and confirming new version works:
git checkout main
git merge development         # Merge development into main
git tag -a v1.1-working -m "New stable version"
git checkout development      # Continue working
```

## Workflow Examples

### Scenario 1: Working on New Feature

```bash
# 1. Start from stable version
git checkout development

# 2. Make changes to files
# Edit files...

# 3. Test your changes
# Test in browser...

# 4. Commit changes
git add .
git commit -m "Added new feature X"

# 5. If something breaks, revert:
git reset --hard main         # Discard all changes
# or
git checkout main             # Switch back to stable
```

### Scenario 2: Experimenting (Want to Try Something Risky)

```bash
# 1. Create experimental branch from development
git checkout development
git checkout -b experiment-feature-x

# 2. Make experimental changes
# Edit files...

# 3. If it works, merge back:
git checkout development
git merge experiment-feature-x
git branch -d experiment-feature-x

# 4. If it fails, just delete the branch:
git checkout development
git branch -D experiment-feature-x
```

### Scenario 3: Something Broke, Need to Revert

```bash
# Quick revert to last working version:
git checkout main

# Or if you want to see what changed first:
git diff main development      # See all differences
git checkout main             # Then switch back
```

## Branch Strategy

```
main (stable)
  ├── v1.0-working (tag)
  └── stable-working (backup branch)
      └── development (active work)
          └── experiment-* (temporary branches)
```

## Best Practices

1. **Always commit working code to `main`**
   - Only merge to `main` when you've tested and confirmed it works
   - Use tags to mark stable versions: `git tag -a v1.0-working -m "Description"`

2. **Work in `development` branch**
   - Make all changes in `development`
   - Test thoroughly before merging to `main`

3. **Create feature branches for experiments**
   - `git checkout -b feature-name`
   - Test the feature
   - Merge or delete based on results

4. **Commit often with clear messages**
   - `git commit -m "Fixed image persistence issue"`
   - `git commit -m "Added new rule: scroll percentage"`

5. **Use tags for major milestones**
   - `git tag -a v1.0-working -m "First stable version"`
   - `git tag -a v1.1-working -m "Added feature X"`

## Common Commands

```bash
# View differences
git diff main development              # Compare branches
git diff HEAD                          # Compare to last commit
git diff main -- path/to/file.php      # Compare specific file

# View history
git log --oneline --graph --all        # Visual history
git log --oneline -10                  # Last 10 commits
git show <commit-hash>                 # See specific commit

# Undo changes
git checkout -- path/to/file.php       # Discard changes to file
git reset HEAD path/to/file.php        # Unstage file
git reset --hard HEAD                  # Discard all uncommitted changes

# Save work without committing
git stash                              # Save changes temporarily
git stash list                         # List saved stashes
git stash pop                          # Restore last stash
git stash drop                         # Delete last stash
```

## Emergency Revert

If everything is broken and you need to get back to working version immediately:

```bash
# Nuclear option - discard everything and go back to stable
git checkout main
git branch -D development
git checkout -b development
```

**Warning**: This permanently deletes all uncommitted work in development branch!

## Creating Backups

Before major changes, create a backup:

```bash
# Create backup branch from current state
git checkout development
git branch backup-before-major-change

# Now you can always go back:
git checkout backup-before-major-change
```

## Summary

- **`main`** = Stable, working version (safe to revert to)
- **`development`** = Active work (where you make changes)
- **Tags** = Marked stable versions (easy to reference)
- **Feature branches** = Experiments (can be deleted if they fail)

Always work in `development`, test thoroughly, then merge to `main` when stable.
