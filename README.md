# Tarsus Lerncoach block for Moodle (block_tarsus)

A course block for the Tarsus AI learning coach. On a
course page with at least one Tarsus activity the viewer can open, it shows:

- the learner's standing per activity: progress, experience points, grade and
  completion;
- a "Continue in …" link to the most recently touched unfinished activity;
- for holders of `block/tarsus:viewsummary` (teachers and managers by
  default) a per-activity course summary: learners started, average
  progress, average grade, completions.

All figures come from the activity's progress table, the gradebook and
activity completion; the block never calls the brain. It stays hidden in a
course without a visible Tarsus activity. It follows the site's light or dark
colour mode.

Requires **local_tarsus** and **mod_tarsus**. See the local_tarsus README for
the whole plugin set.

## Requirements

- Moodle 5.0 or later, tested on 5.2 and 5.3; PHP 8.2 or later.
- local_tarsus and mod_tarsus installed.

## Installation

1. Install local_tarsus and mod_tarsus.
2. Unzip into `blocks/tarsus` (Moodle 5.1+: `public/blocks/tarsus`), or add
   the repository as a Git submodule at that path.
3. Visit *Site administration › Notifications* to install.
4. Turn editing on in a course and add the *Tarsus Lerncoach* block.

## Capabilities

| Capability | Default roles |
|---|---|
| `block/tarsus:addinstance` | Editing teacher, manager |
| `block/tarsus:viewsummary` | Teacher, editing teacher, manager |

## Privacy

The block stores no personal data of its own.

## Development

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite block_tarsus_testsuite
```

## Credits

Developed by lern.link GmbH and Vadym Nersesov. Tarsus and the Flexudy Brain
are products of Flexudy. This plugin is not affiliated with Moodle HQ.

## License

GNU GPL v3 or later: https://www.gnu.org/licenses/
