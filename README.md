# block_tarsus

Course block plugin for the Tarsus ↔ Moodle integration (Lerncoach).

Shows, on a course page with at least one Tarsus activity the viewer can open:

- the learner's standing per activity (progress, XP / LU, grade, completion);
- a "Continue in …" link to the most recently touched unfinished activity;
- for holders of `block/tarsus:viewsummary` (teacher, editing teacher, manager)
  a per-activity course summary: learners started, average progress, average
  grade, completions.

All figures come from `tarsuszero_progress`, the gradebook and completion;
the block never calls the brain. It stays empty (hidden) in a course without
a visible Tarsus activity. Depends on `local_tarsus` and `mod_tarsuszero`.

- **Component:** `block_tarsus`
- **Target:** Moodle 5.2 / 5.3 · PHP 8.3+

See the project technical specification for full design details.

## License

GNU GPL v3 or later — https://www.gnu.org/licenses/
