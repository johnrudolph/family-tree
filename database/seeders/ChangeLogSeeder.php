<?php

namespace Database\Seeders;

use App\Models\ChangeLogEntry;
use Illuminate\Database\Seeder;

/**
 * One-time backfill of the Change Log with a plain-language note for every
 * user-facing change made before the Change Log page itself existed. Safe
 * to re-run — each entry is keyed on its description, so running it twice
 * won't create duplicates.
 */
class ChangeLogSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['2026-09-14 14:30:31', 'Added a real dashboard, plus tools to manage relationships, editors, and details for people who have passed away.'],
            ['2026-09-14 18:01:33', 'Redesigned the family tree page with a search panel and quick relationship editing.'],
            ['2026-09-14 18:17:47', 'Added smart suggestions for linking co-parents and siblings when you add a family member.'],
            ['2026-09-14 19:09:00', 'Fixed hard to read family tree cards in dark mode.'],
            ['2026-09-14 19:23:12', 'Made relationship suggestions easier to review and adjust before saving.'],
            ['2026-09-17 07:37:14', 'Moved the "add relationship" form into a popup and cleaned up the person picker.'],
            ['2026-09-17 07:44:32', 'Added the ability to remove relationships, plus badges showing account and editor status.'],
            ['2026-09-17 07:55:27', 'Made the family tree animate faster and smoother.'],
            ['2026-09-17 07:56:57', 'Siblings and children now stay sorted by birth order.'],
            ['2026-09-17 08:03:56', 'Person pickers are now searchable instead of long dropdown lists.'],
            ['2026-09-17 08:43:47', 'Siblings can now be added and removed as real relationships.'],
            ['2026-09-17 08:55:41', 'You can now mark a new person as deceased and add their date of death right away.'],
            ['2026-09-17 09:51:39', 'Added support for multiple admins and a page to manage them.'],
            ['2026-09-17 10:03:15', 'Bios and stories now use a rich text editor.'],
            ['2026-09-17 10:13:48', 'Added an onboarding step to review and confirm your profile info.'],
            ['2026-09-17 13:44:56', 'The family tree now opens centered on you.'],
            ['2026-09-17 13:48:43', 'People with an account now show an outline on their family tree card.'],
            ['2026-09-17 13:51:25', 'You can now pan the family tree with a trackpad.'],
            ['2026-09-17 13:56:15', 'Added quick search (Cmd+K) to jump straight to anyone\'s page.'],
            ['2026-09-17 14:06:16', 'Replaced the person page\'s relationship list with a mini family tree.'],
            ['2026-09-17 16:51:43', 'Person pages now show how you\'re related to them, like "second cousin, once removed."'],
            ['2026-09-17 16:54:59', 'Added a button to view someone in the full family tree from their page.'],
            ['2026-09-17 16:56:28', 'You can now add a sibling even before any parents are recorded.'],
            ['2026-09-17 17:50:09', 'Added search on the people page (Cmd+F), plus small polish to the tree and birthday banner.'],
            ['2026-09-17 18:01:17', 'Stories can now have a start and end date, a title, and a featured photo.'],
            ['2026-09-17 18:12:00', 'You can now tag people by name inside a story.'],
            ['2026-09-17 18:25:16', 'Added the Timeline page, showing births, deaths, and stories on a zoomable timeline.'],
            ['2026-09-17 19:06:11', 'You can now add gallery photos while creating a story, not just after.'],
            ['2026-09-17 19:21:11', 'Fixed the story popup on the timeline, and removed the card emojis.'],
            ['2026-09-17 19:23:03', 'Fixed broken story and profile photos, and reorganized the person page.'],
            ['2026-09-17 19:38:15', 'Raised photo upload size limits and added clearer messages when an upload fails.'],
            ['2026-09-17 19:55:29', 'Fixed pinch to zoom sometimes zooming the whole page instead of the family tree.'],
            ['2026-09-17 20:17:17', 'Added a click through photo gallery for story photos.'],
            ['2026-09-17 20:19:18', 'Fixed the timeline zoom slider not doing anything.'],
            ['2026-09-27 13:23:24', 'Added a map showing where births, deaths, and stories happened, plus an "on this day" banner and lifespans on the family tree.'],
            ['2026-09-27 14:07:59', 'Added an admin view to help fill in missing information about people.'],
            ['2026-09-27 14:15:49', 'Fixed hard to see tree connector lines in light mode, and divorced spouses now show a dashed line.'],
            ['2026-09-27 14:26:11', 'Map markers in the same location now group together, and the map background is cleaner.'],
            ['2026-09-27 14:26:15', 'Admins can now delete a person, with a confirmation step.'],
            ['2026-09-27 14:29:19', 'Invites now require picking an existing person instead of typing a new name.'],
            ['2026-09-27 14:33:08', 'Your browser tab now shows the person\'s name while you\'re on their page.'],
            ['2026-09-27 15:11:08', 'You can now record a birth or death year without knowing the exact date.'],
            ['2026-09-27 15:18:58', 'Added sex as a field on people.'],
            ['2026-09-27 15:30:21', 'Removed some unnecessary explanation text from accountless people\'s pages.'],
            ['2026-09-27 20:14:04', 'Adding a second parent now offers to mark them as married to the first.'],
            ['2026-09-27 20:39:44', 'Fixed the "on this day" banner looking broken on narrow screens.'],
            ['2026-09-27 21:04:09', 'Added an option to only show your direct relatives on the people, timeline, and map pages.'],
            ['2026-09-28 13:36:07', 'Fixed the family tree opening on the wrong person, and added a button to fit the whole tree on screen.'],
            ['2026-09-28 17:00:00', 'Cleaned up the family tree, timeline, map, and person pages on mobile.'],
            ['2026-09-28 17:48:09', 'Added this Change Log page.'],
            ['2026-09-28 18:40:00', 'The manage admins page now groups people into super admins, admins, and everyone else.'],
            ['2026-09-28 18:45:00', 'Redesigned the dashboard with recent activity, recently added stories, recently joined members, and prompts to finish your own profile.'],
            ['2026-09-28 18:50:00', 'Fixed the sidebar not reaching the bottom of the page on short pages.'],
            ['2026-09-28 18:55:00', 'Put the dashboard\'s activity, stories, and members lists into cards.'],
            ['2026-09-28 19:00:00', 'Redesigned the Stories page as a full-width grid showing each story\'s photo, date, and location.'],
            ['2026-09-28 19:10:00', 'Fixed the family tree\'s "Fit to view" button not working and sitting outside the tree.'],
            ['2026-09-28 19:14:00', 'The birthday and "on this day" banner now only shows on the dashboard, not every page.'],
            ['2026-09-29 12:00:00', 'Stories can now include an audio recording, like an oral history, with a simple player. Stories with audio show a small speaker icon.'],
            ['2026-09-29 13:00:00', 'Editors and admins can now delete a story, with a confirmation step. Its photos and audio are deleted too.'],
            ['2026-09-29 14:00:00', 'The "on this day" card is now a normal dashboard card instead of a big banner.'],
            ['2026-09-29 15:00:00', 'Fixed story and bio text so links show up blue, paragraphs have proper spacing, and headings, lists, and bold or italic text look right.'],
        ];

        foreach ($entries as [$mergedAt, $description]) {
            ChangeLogEntry::query()->firstOrCreate(
                ['description' => $description],
                ['merged_at' => $mergedAt],
            );
        }
    }
}
