        </main>
    </div>
    <script>
        (function () {
            var sidebar = document.getElementById('dashboard-sidebar');
            var toggle = document.getElementById('account-toggle');
            var menu = document.getElementById('account-menu');
            var mobileToggle = document.querySelector('.sidebar-toggle');
            var backdrop = document.querySelector('.sidebar-backdrop');
            var search = document.getElementById('history-search');
            var historyItems = Array.prototype.slice.call(document.querySelectorAll('#history-list li'));
            var historyEmpty = document.getElementById('history-empty');

            function setAccountMenu(open) {
                menu.hidden = !open;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            function setSidebarOpen(open) {
                sidebar.classList.toggle('is-open', open);
                backdrop.classList.toggle('is-visible', open);
                mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                mobileToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            }

            if (menu && !menu.hidden) {
                var apiKeyInput = document.getElementById('api_key_value');
                if (apiKeyInput) apiKeyInput.focus();
            }
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                setAccountMenu(menu.hidden);
            });
            if (menu && toggle) {
                document.querySelectorAll('a[href="#api_key_value"]').forEach(function (link) {
                    link.addEventListener('click', function () {
                        setAccountMenu(true);
                        window.setTimeout(function () {
                            var apiKeyInput = document.getElementById('api_key_value');
                            if (apiKeyInput) apiKeyInput.focus();
                        }, 0);
                    });
                });
            }
            document.addEventListener('click', function (event) {
                if (!menu.hidden && !menu.contains(event.target) && !toggle.contains(event.target)) {
                    setAccountMenu(false);
                }
            });
            mobileToggle.addEventListener('click', function () {
                setSidebarOpen(!sidebar.classList.contains('is-open'));
            });
            backdrop.addEventListener('click', function () {
                setSidebarOpen(false);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    setAccountMenu(false);
                    setSidebarOpen(false);
                }
            });
            search.addEventListener('input', function () {
                var query = search.value.trim().toLowerCase();
                var visibleCount = 0;
                historyItems.forEach(function (item) {
                    var matches = item.dataset.historySearch.indexOf(query) !== -1;
                    item.hidden = !matches;
                    if (matches) visibleCount += 1;
                });
                historyEmpty.hidden = visibleCount !== 0;
            });
            setAccountMenu(<?= $accountMenuOpen ? 'true' : 'false' ?>);
        }());
    </script>
