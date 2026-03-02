/**
 * Calendar Page JS — Himalayan Homestay Bookings
 *
 * Renders a month-view calendar grid, fetches bookings via AJAX,
 * and handles the "Block Dates" modal.
 */
(function($) {
    'use strict';

    const MONTH_NAMES = [
        'January','February','March','April','May','June',
        'July','August','September','October','November','December'
    ];
    const DAY_NAMES = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

    let currentYear  = new Date().getFullYear();
    let currentMonth = new Date().getMonth() + 1; // 1-indexed

    // =========================================================================
    // Init
    // =========================================================================

    $(document).ready(function() {
        loadCalendar();

        $('#hhb-cal-prev').on('click', function() {
            currentMonth--;
            if (currentMonth < 1) { currentMonth = 12; currentYear--; }
            loadCalendar();
        });

        $('#hhb-cal-next').on('click', function() {
            currentMonth++;
            if (currentMonth > 12) { currentMonth = 1; currentYear++; }
            loadCalendar();
        });

        $('#hhb-cal-today').on('click', function() {
            currentYear  = new Date().getFullYear();
            currentMonth = new Date().getMonth() + 1;
            loadCalendar();
        });

        // Block modal
        $('#hhb-add-block-btn').on('click', function() {
            $('#hhb-block-modal').show();
        });
        $('#hhb-block-cancel').on('click', function() {
            $('#hhb-block-modal').hide();
        });
        $('#hhb-block-save').on('click', saveBlock);
    });

    // =========================================================================
    // Load Calendar Data
    // =========================================================================

    function loadCalendar() {
        $('#hhb-cal-title').text(MONTH_NAMES[currentMonth - 1] + ' ' + currentYear);
        $('#hhb-cal-grid').html('<div class="hhb-cal-loading">Loading...</div>');

        $.post(hhbCalendar.ajax_url, {
            action: 'hhb_calendar_data',
            nonce:  hhbCalendar.nonce,
            year:   currentYear,
            month:  currentMonth
        }, function(res) {
            if (res.success) {
                renderGrid(res.data.bookings, res.data.year, res.data.month);
            }
        });
    }

    // =========================================================================
    // Render Grid
    // =========================================================================

    function renderGrid(bookings, year, month) {
        const grid = $('#hhb-cal-grid');
        grid.empty();

        // Header row
        let header = '<div class="hhb-cal-header">';
        DAY_NAMES.forEach(function(d) {
            header += '<div class="hhb-cal-header-cell">' + d + '</div>';
        });
        header += '</div>';
        grid.append(header);

        // Calculate grid
        const firstDay    = new Date(year, month - 1, 1);
        const daysInMonth = new Date(year, month, 0).getDate();
        // getDay() is 0=Sun, we want Mon=0
        let startOffset = firstDay.getDay() - 1;
        if (startOffset < 0) startOffset = 6;

        const today = new Date();
        const todayStr = today.getFullYear() + '-' +
            String(today.getMonth()+1).padStart(2,'0') + '-' +
            String(today.getDate()).padStart(2,'0');

        let body = '<div class="hhb-cal-body">';

        // Empty leading cells
        for (let i = 0; i < startOffset; i++) {
            body += '<div class="hhb-cal-cell hhb-empty"></div>';
        }

        // Day cells
        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = year + '-' + String(month).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const isToday = (dateStr === todayStr);
            body += '<div class="hhb-cal-cell' + (isToday ? ' hhb-today' : '') + '">';
            body += '<div class="hhb-cal-day-num">' + d + '</div>';

            // Find bookings that overlap this day
            bookings.forEach(function(b) {
                if (dateStr >= b.check_in && dateStr < b.check_out) {
                    // Only render start label on the check-in day (or first day of month)
                    if (dateStr === b.check_in || d === 1) {
                        const isBlock = (b.status === 'blocked');
                        const label = isBlock
                            ? '🚫 Blocked: ' + escHtml(b.customer_name)
                            : (b.homestay_title || 'Homestay') + ' — ' + b.customer_name;
                        body += '<div class="hhb-cal-booking status-' + b.status + '" title="' + escHtml(label) + '">';
                        body += '<span>' + escHtml(label) + '</span>';
                        if (isBlock) {
                            body += '<button class="hhb-del-block" data-id="' + b.id + '" title="Remove block" style="float:right;background:none;border:none;cursor:pointer;color:#c00;font-weight:bold;padding:0 4px;">×</button>';
                        }
                        body += '</div>';
                    }
                }
            });

            body += '</div>';
        }

        // Trailing empty cells
        const totalCells = startOffset + daysInMonth;
        const remaining  = totalCells % 7 === 0 ? 0 : (7 - (totalCells % 7));
        for (let i = 0; i < remaining; i++) {
            body += '<div class="hhb-cal-cell hhb-empty"></div>';
        }

        body += '</div>';
        grid.append(body);

        // Bind delete-block buttons
        grid.find('.hhb-del-block').on('click', function(e) {
            e.stopPropagation();
            const blockId = $(this).data('id');
            if (confirm('Remove this date block?')) {
                deleteBlock(blockId);
            }
        });
    }

    // =========================================================================
    // Save Block
    // =========================================================================

    function saveBlock() {
        const btn = $('#hhb-block-save');
        btn.prop('disabled', true).text('Saving...');

        $.post(hhbCalendar.ajax_url, {
            action:      'hhb_add_block',
            nonce:       hhbCalendar.nonce,
            homestay_id: $('#hhb-block-homestay').val(),
            check_in:    $('#hhb-block-from').val(),
            check_out:   $('#hhb-block-to').val(),
            reason:      $('#hhb-block-reason').val()
        }, function(res) {
            btn.prop('disabled', false).text('Block Dates');
            if (res.success) {
                $('#hhb-block-modal').hide();
                loadCalendar();
            } else {
                alert(res.data || 'Error blocking dates.');
            }
        });
    }

    function deleteBlock(blockId) {
        $.post(hhbCalendar.ajax_url, {
            action:   'hhb_delete_block',
            nonce:    hhbCalendar.nonce,
            block_id: blockId
        }, function(res) {
            if (res.success) {
                loadCalendar();
            } else {
                alert(res.data || 'Could not remove block.');
            }
        });
    }

    // =========================================================================
    // Utility
    // =========================================================================

    function escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

})(jQuery);
