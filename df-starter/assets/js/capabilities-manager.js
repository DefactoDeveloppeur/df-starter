document.addEventListener('DOMContentLoaded', function () {
    var groups = document.querySelectorAll('.dfs-cap-group');

    groups.forEach(function (group) {
        var master = group.querySelector('.dfs-cap-group-toggle');
        var boxes = group.querySelectorAll('.dfs-cap-checkbox');
        if (!master || !boxes.length) {
            return;
        }

        function syncMaster() {
            var checked = 0;
            boxes.forEach(function (b) {
                if (b.checked) {
                    checked++;
                }
            });
            master.checked = checked === boxes.length;
            master.indeterminate = checked > 0 && checked < boxes.length;
        }

        master.addEventListener('change', function () {
            boxes.forEach(function (b) {
                b.checked = master.checked;
            });
            master.indeterminate = false;
        });

        boxes.forEach(function (b) {
            b.addEventListener('change', syncMaster);
        });

        syncMaster();
    });

    var search = document.getElementById('dfs-cap-search');
    if (search) {
        search.addEventListener('input', function () {
            var query = search.value.toLowerCase();

            document.querySelectorAll('.dfs-cap-item').forEach(function (item) {
                var cap = (item.dataset.cap || '').toLowerCase();
                item.style.display = cap.indexOf(query) !== -1 ? '' : 'none';
            });

            // Masque les groupes devenus vides.
            groups.forEach(function (group) {
                var visible = group.querySelectorAll('.dfs-cap-item:not([style*="none"])');
                group.style.display = visible.length ? '' : 'none';
            });
        });
    }
});
