# Browser smoke checks

Use the Flarum 2 test installation with this checkout enabled. Prepare an
administrator, two ordinary users, and a moderator with the relevant badge
permissions. Use separate browser profiles or a private window for guest checks.
Reload the page fully between identities; client-side data from an earlier
session is not evidence of the current API response.

## Badge pages and holders

1. Open `/badges`, then click a badge with several holders.
2. Verify names, avatars, badge icons and profile links appear correctly.
3. Open a holder's profile and their Badges tab. Repeat as a guest where viewing
   permissions allow it.
4. Check the browser console and Network panel for errors and failed API calls.

## Visibility and primary badge

1. Give a test user two badges, A and B.
2. As that user, make A the favorite. Verify A appears as the primary badge after
   a full reload and on that user's discussion posts.
3. Remove A from favorites, then hide A. The UI intentionally does not allow
   hiding a current favorite.
4. Verify A remains manageable in the owner's Badges tab but is absent from
   profile/post decorations. A must not become the fallback primary badge.
5. As another ordinary user and as a guest, verify A is absent from the user's
   badge list and from A's holder list. Badge managers may still see the award in
   management lists; it must remain absent from decorations.
6. Show A again and reload. Verify it reappears in the appropriate public views.

## Permissions

1. Temporarily remove public viewing permission for user badges.
2. As a guest and another ordinary user, verify another user's badge decorations
   disappear. The owner should still be able to manage their own awards.
3. Restore the permission and reload.
4. A moderator with manual-award permission should be able to give an ordinary
   manual badge. A badge that changes group membership additionally requires
   core group-edit permission. An insufficiently privileged request must fail
   without awarding the badge or changing the user's groups.

## Discussions

1. Open a large existing discussion and navigate between pages/posts.
2. Verify avatars, primary badges and visible badge lists remain correct for
   multiple authors, including a user with no badges.
3. Check that requests complete without HTTP 500 responses or missing-page
   errors. Browser timing alone does not prove SQL query counts; the automated
   integration tests cover those counts.

## Default badge translations

Only test installation of defaults on an empty/disposable badge setup. Do not
delete existing badges merely to expose the installation button.

1. Select a non-English admin language and install the defaults.
2. Expect three categories and ten badges. Missing `fof-badges.defaults.*`
   translations fall back to the English texts, not raw translation keys.
3. These texts are stored at installation time. Changing the UI language later
   does not retranslate existing records.

When reporting a failure, include the page, user role, reproduction steps,
expected/actual result, and relevant HTTP status/console error. Redact cookies,
authorization headers and CSRF tokens from any screenshots or request exports.
