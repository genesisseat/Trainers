<?php if (!empty($isGuestMode)): ?>
    <script>
        (function () {
            document.documentElement.classList.add('guest-lifecycle-pending');
            var url = new URL(window.location.href);
            var navigation = window.performance && window.performance.getEntriesByType
                ? window.performance.getEntriesByType('navigation')[0]
                : null;
            if (navigation && navigation.type === 'reload' && !url.searchParams.has('guest_draft_clear')) {
                url.searchParams.set('guest_draft_clear', '1');
                window.guestDraftReloadRedirecting = true;
                window.location.replace(url.href);
            }
        }());
    </script>
    <style>html.guest-lifecycle-pending body { visibility: hidden; }</style>
    <script>
        window.guestDraftLifecycle = {
            endpoint: 'guest_draft_lifecycle.php',
            csrfToken: <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        };
    </script>
    <script defer src="assets/guest-draft-lifecycle.js"></script>
<?php endif; ?>
