/**
 * Media Trim Admin JavaScript Batch Worker & Interactive Studio
 *
 * @package Media_Trim
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const vars = window.mediaSweepVars || {};
        let totalAttachments = 0;
        let batchSize = 50;
        let currentOffset = 0;
        let unusedItems = [];
        let filteredItems = [];
        let currentFilter = 'all';
        let currentSort = 'size-desc';
        let reclaimableBytes = 0;

        // UI Selectors
        const btnStartScan = document.getElementById('mtrim_btn_start_scan');
        const progressWrap = document.getElementById('mtrim_progress_wrap');
        const progressFill = document.getElementById('mtrim_progress_fill');
        const progressLabel = document.getElementById('mtrim_progress_label');
        const progressPercent = document.getElementById('mtrim_progress_percent');

        const statTotal = document.getElementById('mtrim_stat_total');
        const statUnused = document.getElementById('mtrim_stat_unused');
        const statReclaimable = document.getElementById('mtrim_stat_reclaimable');
        const statQuarantined = document.getElementById('mtrim_stat_quarantined');

        const badgeUnused = document.getElementById('mtrim_badge_unused');
        const badgeQuarantined = document.getElementById('mtrim_badge_quarantined');
        const unusedGrid = document.getElementById('mtrim_unused_grid');
        const qList = document.getElementById('mtrim_q_list');

        const btnSelectAll = document.getElementById('mtrim_btn_select_all');
        const btnQuarantineSelected = document.getElementById('mtrim_btn_quarantine_selected');
        const selectionSummary = document.getElementById('mtrim_selection_summary');

        const btnSelectAllQ = document.getElementById('mtrim_btn_select_all_q');
        const btnRestoreSelected = document.getElementById('mtrim_btn_restore_selected');
        const btnDeleteSelected = document.getElementById('mtrim_btn_delete_selected');

        // Tab Navigation
        const tabBtns = document.querySelectorAll('.mtrim-tab-btn');
        const tabPanes = document.querySelectorAll('.mtrim-tab-pane');
        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = this.getAttribute('data-tab');
                tabBtns.forEach(function (b) { b.classList.remove('active'); });
                tabPanes.forEach(function (p) { p.classList.remove('active'); });
                this.classList.add('active');
                const pane = document.getElementById(target);
                if (pane) { pane.classList.add('active'); }
            });
        });

        // Toast Helper
        function showToast(title, sub) {
            const toast = document.getElementById('mtrim_toast');
            if (!toast) return;
            toast.querySelector('.mtrim-toast-title').textContent = title;
            toast.querySelector('.mtrim-toast-sub').textContent = sub;
            toast.style.display = 'flex';
            setTimeout(function () {
                toast.style.display = 'none';
            }, 3500);
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // 1. START SCAN PROCESS
        if (btnStartScan) {
            btnStartScan.addEventListener('click', function () {
                btnStartScan.disabled = true;
                progressWrap.style.display = 'block';
                progressFill.style.width = '0%';
                progressPercent.textContent = '0%';
                progressLabel.textContent = vars.i18n.scanning;
                unusedItems = [];
                reclaimableBytes = 0;
                currentOffset = 0;

                // Request Scan Overview
                const formData = new FormData();
                formData.append('action', 'mtrim_get_scan_overview');
                formData.append('nonce', vars.nonce);

                fetch(vars.ajaxUrl, { method: 'POST', body: formData })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (res.success) {
                            totalAttachments = res.data.total;
                            batchSize = res.data.batch_size || 50;
                            statTotal.textContent = totalAttachments;
                            processNextBatch();
                        } else {
                            alert(res.data.message || 'Error initializing scan.');
                            btnStartScan.disabled = false;
                        }
                    })
                    .catch(function () {
                        btnStartScan.disabled = false;
                    });
            });
        }

        // Process batches sequentially
        function processNextBatch() {
            if (totalAttachments === 0) {
                finishScan();
                return;
            }

            const formData = new FormData();
            formData.append('action', 'mtrim_scan_batch');
            formData.append('offset', currentOffset);
            formData.append('limit', batchSize);
            formData.append('nonce', vars.nonce);

            fetch(vars.ajaxUrl, { method: 'POST', body: formData })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (res.success && res.data.results) {
                        res.data.results.forEach(function (item) {
                            if (!item.is_used) {
                                unusedItems.push(item);
                                reclaimableBytes += (item.filesize || 0);
                            }
                        });

                        currentOffset += batchSize;
                        const percent = Math.min(100, Math.round((currentOffset / totalAttachments) * 100));
                        progressFill.style.width = percent + '%';
                        progressPercent.textContent = percent + '%';
                        progressLabel.textContent = 'Scanned ' + Math.min(currentOffset, totalAttachments) + ' of ' + totalAttachments + ' media files...';

                        // Update live stats
                        statUnused.textContent = unusedItems.length;
                        badgeUnused.textContent = unusedItems.length;
                        statReclaimable.textContent = formatBytes(reclaimableBytes);

                        if (currentOffset < totalAttachments) {
                            processNextBatch();
                        } else {
                            finishScan();
                        }
                    } else {
                        finishScan();
                    }
                })
                .catch(function () {
                    finishScan();
                });
        }

        function finishScan() {
            btnStartScan.disabled = false;
            progressPercent.textContent = '100%';
            progressLabel.textContent = vars.i18n.scanComplete;
            showToast('Scan Completed', 'Found ' + unusedItems.length + ' potentially unused media files.');
            applyFilterAndSort();
        }

        // Filter pills listener
        const filterPills = document.querySelectorAll('.mtrim-filter-pill');
        filterPills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                filterPills.forEach(function (p) { p.classList.remove('active'); });
                this.classList.add('active');
                currentFilter = this.getAttribute('data-filter');
                applyFilterAndSort();
            });
        });

        // Sort select listener
        const sortSelect = document.getElementById('mtrim_sort_select');
        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                currentSort = this.value;
                applyFilterAndSort();
            });
        }

        function applyFilterAndSort() {
            // 1. Filter
            const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];
            const docExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'];
            const mediaExts = ['mp4', 'm4v', 'mov', 'wmv', 'avi', 'mp3', 'wav', 'ogg'];

            filteredItems = unusedItems.filter(function (item) {
                const ext = (item.ext || '').toLowerCase();
                if (currentFilter === 'images') {
                    return imageExts.indexOf(ext) !== -1;
                } else if (currentFilter === 'docs') {
                    return docExts.indexOf(ext) !== -1;
                } else if (currentFilter === 'media') {
                    return mediaExts.indexOf(ext) !== -1;
                }
                return true;
            });

            // 2. Sort
            filteredItems.sort(function (a, b) {
                if (currentSort === 'size-desc') {
                    return (b.filesize || 0) - (a.filesize || 0);
                } else if (currentSort === 'size-asc') {
                    return (a.filesize || 0) - (b.filesize || 0);
                } else if (currentSort === 'id-desc') {
                    return b.id - a.id;
                } else if (currentSort === 'id-asc') {
                    return a.id - b.id;
                }
                return 0;
            });

            renderUnusedGrid();
        }

        function renderUnusedGrid() {
            if (!unusedGrid) return;
            unusedGrid.innerHTML = '';

            if (filteredItems.length === 0) {
                unusedGrid.innerHTML = '<div class="mtrim-empty-state"><span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span><strong>' + (unusedItems.length === 0 ? vars.i18n.noUnused : 'No items match this filter category.') + '</strong></div>';
                selectionSummary.textContent = unusedItems.length === 0 ? 'All assets are in use!' : '0 items match category';
                return;
            }

            selectionSummary.textContent = filteredItems.length + ' file(s) displayed (' + formatBytes(reclaimableBytes) + ' total potential)';

            filteredItems.forEach(function (item) {
                const card = document.createElement('div');
                card.className = 'mtrim-item-card';
                card.setAttribute('data-id', item.id);

                const thumbSrc = item.thumb || '';
                const thumbHtml = thumbSrc ? '<img src="' + thumbSrc + '" class="mtrim-item-thumb" alt="' + item.filename + '"/>' : '<span class="dashicons dashicons-media-default" style="font-size:32px;color:#94a3b8;"></span>';

                card.innerHTML =
                    '<div class="mtrim-item-thumb-wrap">' +
                        '<input type="checkbox" class="mtrim-item-checkbox" value="' + item.id + '" />' +
                        thumbHtml +
                    '</div>' +
                    '<div class="mtrim-item-details">' +
                        '<span class="mtrim-item-title" title="' + item.filename + '">' + item.filename + '</span>' +
                        '<div class="mtrim-item-meta">' +
                            '<span>' + formatBytes(item.filesize) + '</span>' +
                            '<span>ID: ' + item.id + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="mtrim-item-actions">' +
                        '<button type="button" class="mtrim-item-whitelist" data-id="' + item.id + '">Ignore / Whitelist</button>' +
                    '</div>';

                unusedGrid.appendChild(card);
            });

            bindUnusedEvents();
        }

        function bindUnusedEvents() {
            const checks = document.querySelectorAll('.mtrim-item-checkbox');
            checks.forEach(function (chk) {
                chk.addEventListener('change', function () {
                    const card = this.closest('.mtrim-item-card');
                    if (this.checked) {
                        card.classList.add('selected');
                    } else {
                        card.classList.remove('selected');
                    }
                    updateBulkState();
                });
            });

            const whitelistBtns = document.querySelectorAll('.mtrim-item-whitelist');
            whitelistBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const formData = new FormData();
                    formData.append('action', 'mtrim_toggle_whitelist');
                    formData.append('id', id);
                    formData.append('nonce', vars.nonce);

                    fetch(vars.ajaxUrl, { method: 'POST', body: formData })
                        .then(function (res) { return res.json(); })
                        .then(function (res) {
                            if (res.success) {
                                const card = document.querySelector('.mtrim-item-card[data-id="' + id + '"]');
                                if (card) card.remove();
                                unusedItems = unusedItems.filter(function (i) { return i.id != id; });
                                statUnused.textContent = unusedItems.length;
                                badgeUnused.textContent = unusedItems.length;
                                showToast('Asset Whitelisted', 'Asset #' + id + ' will be permanently excluded from future scans.');
                            }
                        });
                });
            });
        }

        function updateBulkState() {
            const selected = document.querySelectorAll('.mtrim-item-checkbox:checked');
            btnQuarantineSelected.disabled = selected.length === 0;
            selectionSummary.textContent = selected.length + ' of ' + unusedItems.length + ' selected';
        }

        if (btnSelectAll) {
            btnSelectAll.addEventListener('click', function () {
                const checks = document.querySelectorAll('.mtrim-item-checkbox');
                const allChecked = Array.from(checks).every(function (c) { return c.checked; });
                checks.forEach(function (c) {
                    c.checked = !allChecked;
                    const card = c.closest('.mtrim-item-card');
                    if (!allChecked) card.classList.add('selected');
                    else card.classList.remove('selected');
                });
                updateBulkState();
            });
        }

        // Quarantine Selected
        if (btnQuarantineSelected) {
            btnQuarantineSelected.addEventListener('click', function () {
                const selected = Array.from(document.querySelectorAll('.mtrim-item-checkbox:checked')).map(function (c) { return c.value; });
                if (selected.length === 0) return;

                if (!confirm(vars.i18n.quarantineConfirm)) return;

                btnQuarantineSelected.disabled = true;

                const formData = new FormData();
                formData.append('action', 'mtrim_quarantine_items');
                formData.append('nonce', vars.nonce);
                selected.forEach(function (id) { formData.append('ids[]', id); });

                fetch(vars.ajaxUrl, { method: 'POST', body: formData })
                    .then(function (res) { return res.json(); })
                    .then(function (res) {
                        if (res.success) {
                            showToast('Quarantined Successfully', res.data.quarantined_count + ' files moved to safe quarantine.');
                            statQuarantined.textContent = res.data.total_quarantined;
                            badgeQuarantined.textContent = res.data.total_quarantined;
                            // Remove from DOM
                            selected.forEach(function (id) {
                                const card = document.querySelector('.mtrim-item-card[data-id="' + id + '"]');
                                if (card) card.remove();
                                unusedItems = unusedItems.filter(function (i) { return i.id != id; });
                            });
                            statUnused.textContent = unusedItems.length;
                            badgeUnused.textContent = unusedItems.length;
                            updateBulkState();
                            setTimeout(function () { window.location.reload(); }, 1200);
                        } else {
                            alert(res.data.message || 'Quarantine failed.');
                        }
                    });
            });
        }

        // Quarantine Tab Event Handlers (Restore & Delete)
        if (qList) {
            qList.addEventListener('click', function (e) {
                if (e.target.classList.contains('mtrim-single-restore')) {
                    const id = e.target.getAttribute('data-id');
                    if (!confirm(vars.i18n.restoreConfirm)) return;
                    handleQuarantineAction('mtrim_restore_items', [id], 'File Restored');
                } else if (e.target.classList.contains('mtrim-single-delete')) {
                    const id = e.target.getAttribute('data-id');
                    if (!confirm(vars.i18n.deleteConfirm)) return;
                    handleQuarantineAction('mtrim_delete_items', [id], 'File Permanently Deleted');
                }
            });
        }

        function handleQuarantineAction(action, ids, successTitle) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', vars.nonce);
            ids.forEach(function (id) { formData.append('ids[]', id); });

            fetch(vars.ajaxUrl, { method: 'POST', body: formData })
                .then(function (res) { return res.json(); })
                .then(function (res) {
                    if (res.success) {
                        showToast(successTitle, 'Action processed successfully.');
                        setTimeout(function () { window.location.reload(); }, 1000);
                    }
                });
        }
    });
})();
