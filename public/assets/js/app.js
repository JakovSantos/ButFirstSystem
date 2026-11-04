/* =====================================================================
   BUT FIRST, SYSTEM — client behaviour
   Port of the C# event handlers in Form1.cs / Form2.cs (same rules, same
   messages). The PHP server re-validates everything.
   ===================================================================== */
(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
    var store = {
        get: function (k) { try { return window.sessionStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { window.sessionStorage.setItem(k, v); } catch (e) { /* ignore */ } }
    };
    function parseJson(text, fallback) { try { return JSON.parse(text); } catch (e) { return fallback; } }
    function isNumber(v) { return String(v).trim() !== '' && !isNaN(Number(v)) && isFinite(Number(v)); }
    function peso(v) {
        var n = Number(v) || 0;
        return (n < 0 ? '-₱' : '₱') + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function on(sel, evt, fn) { var el = $(sel); if (el) { el.addEventListener(evt, fn); } return el; }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

    var ICONS = {
        information: '<svg class="ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>',
        warning: '<svg class="ico" viewBox="0 0 24 24"><path d="M12 3l9.5 17h-19z"/><path d="M12 10v4M12 17h.01"/></svg>',
        error: '<svg class="ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg>',
        question: '<svg class="ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 015 0c0 1.7-2.5 2-2.5 4M12 17h.01"/></svg>',
        check: '<svg class="ico" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/></svg>'
    };

    /* -----------------------------------------------------------------
       MessageBox.Show(text, caption, icon, buttons)  → dialog
       ----------------------------------------------------------------- */
    var MsgBox = (function () {
        var overlay = $('#msgbox-overlay');
        var queue = Promise.resolve();

        function open(text, caption, icon, buttons) {
            return new Promise(function (resolve) {
                buttons = buttons || ['OK'];
                $('#msgbox-caption').textContent = caption || '';
                $('#msgbox-text').textContent = text || '';
                var iconEl = $('#msgbox-icon');
                iconEl.className = 'dialog-icon ' + (icon || 'information');
                iconEl.innerHTML = ICONS[icon] || ICONS.information;
                var box = $('#msgbox-buttons');
                box.innerHTML = '';
                var lastFocus = document.activeElement;
                var cancel = buttons.indexOf('No') >= 0 ? 'No' : buttons[buttons.length - 1];

                function done(result) {
                    overlay.hidden = true;
                    document.removeEventListener('keydown', onKey, true);
                    overlay.removeEventListener('click', onBackdrop);
                    if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) { /* ignore */ } }
                    resolve(result);
                }
                function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); done(cancel); } }
                function onBackdrop(e) { if (e.target === overlay) { done(cancel); } }

                // Primary action on the right, like modern dialogs.
                buttons.slice().reverse().forEach(function (label) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = label;
                    var primary = label === buttons[0];
                    var destructive = primary && icon === 'warning' && buttons.length > 1;
                    b.className = 'btn ' + (primary ? (destructive ? 'btn-danger' : 'btn-primary') : 'btn-outline');
                    b.addEventListener('click', function () { done(label); });
                    box.appendChild(b);
                });
                document.addEventListener('keydown', onKey, true);
                overlay.addEventListener('click', onBackdrop);
                overlay.hidden = false;
                box.lastChild.focus();
            });
        }

        return {
            show: function (text, caption, icon, buttons) {
                var p = queue.then(function () { return open(text, caption, icon, buttons); });
                queue = p.catch(function () {});
                return p;
            }
        };
    }());
    window.MsgBox = MsgBox;

    /* Success messages appear as a toast instead of blocking the screen. */
    function toast(text, caption) {
        var host = $('#toasts');
        if (!host) { return MsgBox.show(text, caption, 'information'); }
        var el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'status');
        el.innerHTML = ICONS.check + '<div><strong>' + esc(caption) + '</strong><span>' + esc(text) + '</span></div>';
        host.appendChild(el);
        setTimeout(function () { el.classList.add('leaving'); setTimeout(function () { el.remove(); }, 250); }, 4200);
        return Promise.resolve('OK');
    }

    function showFlash() {
        var f = $('#flash');
        if (!f) { return Promise.resolve(); }
        var icon = f.getAttribute('data-icon');
        var text = f.getAttribute('data-message'), caption = f.getAttribute('data-caption');
        return icon === 'information' ? toast(text, caption) : MsgBox.show(text, caption, icon);
    }

    /* Sign out (closing the main window) */
    $$('.js-logout').forEach(function (b) {
        b.addEventListener('click', function () { $('#logout-form').submit(); });
    });

    /* -----------------------------------------------------------------
       LOGIN (Form1)
       ----------------------------------------------------------------- */
    var loginForm = $('#login-form');
    if (loginForm) {
        var txtUser = $('#txtUser'), txtPassword = $('#txtPassword');
        loginForm.addEventListener('submit', function (e) {
            if (txtUser.value.trim() === '' || txtPassword.value.trim() === '') {
                e.preventDefault();
                MsgBox.show('Please enter both username and password.', 'Login Error', 'warning');
            }
        });
        $('#btnTogglePw').addEventListener('click', function () {
            var show = txtPassword.type === 'password';
            txtPassword.type = show ? 'text' : 'password';
            this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
        $('#btnExit').addEventListener('click', function () { // Application.Exit()
            txtUser.value = '';
            txtPassword.value = '';
            window.close();
            setTimeout(function () { $('#closed-note').hidden = false; }, 50);
        });
        showFlash().then(function () {
            var f = $('#flash');
            if (f && f.getAttribute('data-caption') === 'Login Failed') {
                txtPassword.value = '';
                txtPassword.focus();
            }
        });
        return;
    }

    if (!$('#main-window')) { showFlash(); return; }

    /* -----------------------------------------------------------------
       MAIN WINDOW (Form2)
       ----------------------------------------------------------------- */
    var old = parseJson($('#old-input').getAttribute('data-json'), {}) || {};
    var state = $('#page-state');

    /* ---------- Module navigation (TabControl) ---------- */
    $$('.nav-item[data-tab]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            selectTab(link.getAttribute('data-tab'));
        });
    });
    function selectTab(name) {
        $$('.nav-item[data-tab]').forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-tab') === name); });
        $$('[data-tabpage]').forEach(function (p) {
            var on = p.getAttribute('data-tabpage') === name;
            p.hidden = !on;
            if (on) { document.title = p.getAttribute('data-title') + ' · But First Coffee'; }
        });
        window.scrollTo(0, 0);
        try { history.replaceState(null, '', '/' + name); } catch (e) { /* ignore */ }
    }
    var current = $('[data-tabpage]:not([hidden])');
    if (current) { document.title = current.getAttribute('data-title') + ' · But First Coffee'; }

    /* ---------- NumericUpDown → stepper ---------- */
    $$('.stepper').forEach(function (box) {
        var input = $('input', box);
        var min = Number(input.getAttribute('data-min') || 0);
        var max = Number(input.getAttribute('data-max') || 100);
        function clamp() {
            var v = parseInt(input.value, 10);
            if (isNaN(v)) { v = min; }
            input.value = String(Math.max(min, Math.min(max, v)));
        }
        function step(d) {
            if (box.classList.contains('disabled') || input.readOnly) { return; }
            clamp();
            input.value = String(Math.max(min, Math.min(max, Number(input.value) + d)));
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        $$('button', box).forEach(function (b) {
            b.addEventListener('mousedown', function (e) { e.preventDefault(); });
            b.addEventListener('click', function () { step(Number(b.getAttribute('data-step'))); });
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown') { e.preventDefault(); step(e.key === 'ArrowUp' ? 1 : -1); }
            else if (e.key.length === 1 && !/[0-9]/.test(e.key) && !e.ctrlKey && !e.metaKey) { e.preventDefault(); }
        });
        input.addEventListener('blur', clamp);
    });
    function setNumEnabled(input, enabled) {
        input.readOnly = !enabled;
        input.closest('.stepper').classList.toggle('disabled', !enabled);
    }

    /* ---------- DataGridView → selectable table ---------- */
    function Grid(el, onCellClick) {
        var rows = $$('tbody tr[data-row]', el);
        var self = { el: el, current: null };

        self.select = function (tr, fire) {
            rows.forEach(function (r) { r.classList.remove('selected'); r.removeAttribute('aria-selected'); });
            tr.classList.add('selected');
            tr.setAttribute('aria-selected', 'true');
            self.current = tr;
            if (fire && onCellClick) { onCellClick(parseJson(tr.getAttribute('data-row'), null), tr); }
        };
        self.clear = function () {
            rows.forEach(function (r) { r.classList.remove('selected'); r.removeAttribute('aria-selected'); });
            self.current = null;
        };
        self.find = function (attr, value) {
            for (var i = 0; i < rows.length; i++) { if (rows[i].getAttribute(attr) === String(value)) { return rows[i]; } }
            return null;
        };
        self.data = function () { return self.current ? parseJson(self.current.getAttribute('data-row'), null) : null; };
        self.count = function () { return rows.length; };

        rows.forEach(function (tr) { tr.tabIndex = 0; });
        el.addEventListener('click', function (e) {
            var tr = e.target.closest('tbody tr[data-row]');
            if (tr) { self.select(tr, true); }
        });
        el.addEventListener('keydown', function (e) {
            var tr = e.target.closest('tbody tr[data-row]');
            if (tr && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); self.select(tr, true); }
        });
        return self;
    }

    function clearInvalid(root) { $$('.invalid', root).forEach(function (el) { el.classList.remove('invalid'); }); }
    function markInvalid(el) { (el.closest('.stepper') || el).classList.add('invalid'); }
    function setMode(el, kind, text) { el.className = 'badge ' + kind; el.textContent = text; }

    /* =================================================================
       INVENTORY
       ================================================================= */
    var inv = {
        form: $('#product-form'),
        name: $('#txtProductName'), category: $('#cmbCategory'), qty: $('#udQuantity'),
        price: $('#txtPrice'), date: $('#dtDateAdded'), supplier: $('#txtSupplier'),
        id: $('#txtProductID'), path: $('#txtFilePath'), pathLabel: $('#filePathLabel'), hidPath: $('#hidImagePath'),
        file: $('#fileImage'), pbox: $('#pboxProductImage'), btnAdd: $('#btnAdd'), mode: $('#invMode'),
        editProductId: -1, isEditMode: false
    };
    var today = inv.date.value;

    var inventoryGrid = Grid($('#inventoryGrid'), function (row) {
        // inventoryGrid_CellClick: populate + lock (view only)
        if (!row) { return; }
        inv.editProductId = row.product_id;
        inv.isEditMode = false;
        fillProduct(row);
        lockProductFields(true);
        inv.btnAdd.textContent = 'Add';
        setMode(inv.mode, 'view', 'Viewing #' + row.product_id);
        if (window.matchMedia('(max-width: 1100px)').matches) { inv.form.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });

    function showImage(url) {
        var img = $('img', inv.pbox);
        if (url) { img.src = url; img.hidden = false; } else { img.removeAttribute('src'); img.hidden = true; }
        inv.pbox.classList.toggle('has-image', !!url);
        $('.empty', inv.pbox).hidden = !!url;
    }
    function setPath(p) {
        inv.path.value = p || '';
        inv.pathLabel.textContent = p ? p.split('/').pop() : 'No image selected';
    }
    function fillProduct(row) {
        inv.id.value = row.product_id;
        inv.name.value = row.product_name;
        inv.category.value = row.category;
        inv.price.value = row.price;
        inv.qty.value = row.quantity;
        inv.supplier.value = row.supplier || '';
        if (row.date_added) { inv.date.value = row.date_added; }
        setPath(row.image_path);
        inv.hidPath.value = row.image_path || '';
        inv.file.value = '';
        showImage(row.image_url);
        clearInvalid(inv.form);
    }
    function lockProductFields(lock) { // LockProductFields
        inv.id.readOnly = true;
        inv.name.readOnly = lock;
        inv.category.disabled = lock;
        inv.price.readOnly = lock;
        setNumEnabled(inv.qty, !lock);
        inv.supplier.readOnly = lock;
        inv.date.disabled = lock;
    }
    function clearFields(keepSearch) { // ClearFields
        inv.id.value = ''; inv.name.value = ''; inv.category.value = ''; inv.price.value = '';
        inv.qty.value = '0'; inv.supplier.value = ''; inv.date.value = today;
        setPath(''); inv.hidPath.value = ''; inv.file.value = '';
        showImage('');
        if (!keepSearch) { $('#txtSearch').value = ''; }
        inv.btnAdd.textContent = 'Add';
        inv.editProductId = -1;
        inv.isEditMode = false;
        lockProductFields(false);
        clearInvalid(inv.form);
        inventoryGrid.clear();
        setMode(inv.mode, 'new', 'New product');
    }

    // Price: digits and a single '.' (txtPrice_KeyPress)
    inv.price.addEventListener('keydown', function (e) {
        if (e.key.length !== 1 || e.ctrlKey || e.metaKey) { return; }
        if (!/[0-9.]/.test(e.key) || (e.key === '.' && inv.price.value.indexOf('.') > -1)) { e.preventDefault(); }
    });

    // Add (btnAdd_Click): insert, or update when a product is selected/being edited
    inv.form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearInvalid(inv.form);
        var bad = [];
        if (inv.name.value.trim() === '') { bad.push(inv.name); }
        if (inv.category.value === '') { bad.push(inv.category); }
        if (inv.price.value.trim() === '' || !isNumber(inv.price.value)) { bad.push(inv.price); }
        if (bad.length) {
            bad.forEach(markInvalid);
            MsgBox.show('Please correct the highlighted errors.', 'Validation Error', 'error').then(function () { bad[0].focus(); });
            return;
        }
        inv.form.action = inv.editProductId === -1 ? '/inventory' : '/inventory/' + inv.editProductId;
        inv.category.disabled = false;
        inv.date.disabled = false;
        inv.btnAdd.disabled = true;
        inv.form.submit();
    });

    // Edit (btnUpdate_Click)
    $('#btnUpdate').addEventListener('click', function () {
        var row = inventoryGrid.data();
        if (!row) {
            MsgBox.show('Please select a product to edit first.', 'No Selection', 'warning');
            return;
        }
        inv.editProductId = row.product_id;
        inv.isEditMode = true;
        fillProduct(row);
        lockProductFields(false);
        inv.btnAdd.textContent = 'Save changes';
        setMode(inv.mode, 'edit', 'Editing #' + row.product_id);
        inv.name.focus();
    });

    // Remove (btnRemove_Click)
    $('#btnRemove').addEventListener('click', function () {
        var row = inventoryGrid.data();
        if (!row) {
            MsgBox.show('Please select a product to remove.', 'No Selection', 'warning');
            return;
        }
        MsgBox.show('Are you sure you want to remove this product?\n' + row.product_name, 'Confirm Delete', 'warning', ['Yes', 'No']).then(function (r) {
            if (r !== 'Yes') { return; }
            var f = $('#remove-form');
            f.action = '/inventory/' + row.product_id + '/delete';
            f.submit();
        });
    });

    // Browse Image (btnBrowse_Click)
    function browse() {
        if (inv.editProductId !== -1 && !inv.isEditMode) {
            MsgBox.show("Please click 'Edit' before browsing for an image.", 'Edit Mode Required', 'information');
            return;
        }
        inv.file.click();
    }
    $('#btnBrowse').addEventListener('click', browse);
    inv.pbox.addEventListener('click', browse);
    inv.pbox.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); browse(); } });
    inv.file.addEventListener('change', function () {
        var f = inv.file.files && inv.file.files[0];
        if (!f) { return; }
        if (!/\.(jpe?g|png|bmp)$/i.test(f.name)) {
            inv.file.value = '';
            MsgBox.show('Image Files (*.jpg; *.jpeg; *.png; *.bmp) only.', 'Select an Image', 'warning');
            return;
        }
        setPath(f.name);
        showImage(URL.createObjectURL(f));
    });

    // New product (ClearFields without reloading)
    $('#btnNewProduct').addEventListener('click', function () { clearFields(true); inv.name.focus(); });

    // Load (btnLoad_Click) and Clear (btnClear_Inv_Click) reload the list
    $('#btnLoad').addEventListener('click', function () { clearFields(); window.location.href = '/inventory'; });
    $('#btnClear_Inv').addEventListener('click', function () { clearFields(); window.location.href = '/inventory'; });

    // Search (txtSearch_TextChanged -> ApplySearchFilter)
    var search = $('#txtSearch'), searchTimer = null;
    search.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            var q = search.value.trim();
            window.location.href = '/inventory' + (q ? '?search=' + encodeURIComponent(q) : '');
        }, 400);
    });
    $('#search-form').addEventListener('submit', function (e) { e.preventDefault(); });
    if (search.value !== '' && !$('[data-tabpage="inventory"]').hidden) {
        search.focus();
        search.setSelectionRange(search.value.length, search.value.length);
    }

    // Print Summary (btnPrintInventorySum_Click)
    $('#btnPrintInventorySum').addEventListener('click', function () {
        if (inventoryGrid.count() === 0) {
            MsgBox.show('No inventory data to print.', 'No Data', 'information');
            return;
        }
        var q = search.value.trim();
        window.open('/inventory/print' + (q ? '?search=' + encodeURIComponent(q) : ''), '_blank');
    });

    // After add/update the C# app calls ClearFields(); highlight the saved row.
    var invSel = state.getAttribute('data-inventory-selected');
    if (invSel) {
        var tr = inventoryGrid.find('data-id', invSel);
        if (tr) {
            tr.classList.add('selected');
            setTimeout(function () { tr.classList.remove('selected'); }, 2500);
            tr.scrollIntoView({ block: 'nearest' });
        }
        try { history.replaceState(null, '', window.location.pathname); } catch (e) { /* ignore */ }
    }

    // Re-fill after a server-side validation error
    if (old.inventory) {
        var o = old.inventory;
        inv.name.value = o.product_name || ''; inv.category.value = o.category || ''; inv.price.value = o.price || '';
        inv.qty.value = o.quantity || 0; inv.supplier.value = o.supplier || ''; inv.date.value = o.date_added || today;
        inv.hidPath.value = o.image_path || ''; setPath(o.image_path || '');
        if (old.editing) {
            inv.editProductId = old.editing; inv.isEditMode = true; inv.id.value = old.editing;
            inv.btnAdd.textContent = 'Save changes'; setMode(inv.mode, 'edit', 'Editing #' + old.editing);
        }
        var map = { product_name: inv.name, category: inv.category, price: inv.price, quantity: inv.qty, date_added: inv.date };
        Object.keys(old.errors || {}).forEach(function (k) { if (map[k]) { markInvalid(map[k]); } });
    }

    /* =================================================================
       EMPLOYEE SCHEDULE
       ================================================================= */
    var sch = {
        employee: $('#txtEmployee'), schedule: $('#txtSchedule'), brk: $('#txtBreak'),
        duties: $('#txtDuties'), dayoff: $('#cmbDayoff'), start: $('#dtStartDate'), end: $('#dtEndDate')
    };

    var btnSaveName = $('#btnSaveName');
    var selectedShift = null;
    var shiftGrid = Grid($('#shiftGrid'), function (row) { // shiftGrid_CellClick
        if (!row) { return; }
        selectedShift = row;
        if (btnSaveName) { btnSaveName.disabled = true; }
        sch.employee.value = row.names;
        sch.schedule.value = row.schedule;
        sch.brk.value = row.breaks;
        sch.duties.value = row.duties;
        sch.dayoff.value = row.day_off || '';
        if (window.matchMedia('(max-width: 1100px)').matches) { $('#dayoff-form').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });

    [['sched_start', sch.start], ['sched_end', sch.end]].forEach(function (pair) {
        var saved = store.get(pair[0]);
        if (saved && !new URLSearchParams(window.location.search).get(pair[0])) { pair[1].value = saved; }
        pair[1].addEventListener('change', function () { store.set(pair[0], pair[1].value); syncPeriodInputs(); });
    });
    function syncPeriodInputs() {
        $$('.js-sched-start').forEach(function (i) { i.value = sch.start.value; });
        $$('.js-sched-end').forEach(function (i) { i.value = sch.end.value; });
    }
    syncPeriodInputs();

    // Rename (admin): the new name is saved on the schedule and the linked payroll record.
    if (btnSaveName) {
        sch.employee.addEventListener('input', function () {
            btnSaveName.disabled = !selectedShift || sch.employee.value.trim() === '' ||
                sch.employee.value.trim() === selectedShift.names;
        });
        sch.employee.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); if (!btnSaveName.disabled) { btnSaveName.click(); } }
        });
        btnSaveName.addEventListener('click', function () {
            var name = sch.employee.value.trim().replace(/\s+/g, ' ');
            if (!selectedShift) {
                MsgBox.show('Please select an employee from the grid.', 'Validation Error', 'warning');
                return;
            }
            if (name === '') {
                markInvalid(sch.employee);
                MsgBox.show('Please enter a name.', 'Validation Error', 'warning');
                return;
            }
            MsgBox.show('Rename ' + selectedShift.names + ' to ' + name + '?\nThe new name will show on the schedule, payroll and printouts.',
                'Rename Employee', 'question', ['Rename', 'Cancel']).then(function (r) {
                if (r !== 'Rename') { return; }
                var f = $('#rename-form');
                f.action = '/schedule/' + selectedShift.id + '/name';
                $('#hidNewName').value = name;
                syncPeriodInputs();
                f.submit();
            });
        });
    }

    // Request (btnRequest_Click)
    $('#dayoff-form').addEventListener('submit', function (e) {
        if (!selectedShift || sch.employee.value.trim() === '') {
            e.preventDefault();
            MsgBox.show('Please select an employee from the grid.', 'Validation Error', 'warning');
            return;
        }
        if (sch.dayoff.value === '') {
            e.preventDefault();
            MsgBox.show('Please select a day off from the dropdown.', 'Validation Error', 'warning');
            return;
        }
        $('#hidDayoffName').value = selectedShift.names; // the saved name, even if the box was edited
        syncPeriodInputs();
    });

    $('#randomize-form').addEventListener('submit', syncPeriodInputs);

    // Reset Dayoff (btnResetDayoff_Click)
    $('#btnResetDayoff').addEventListener('click', function () {
        MsgBox.show('Are you sure you want to reset all day off assignments?\nThis will clear the Day Off column for all employees.',
            'Confirm Reset', 'question', ['Yes', 'No']).then(function (r) {
            if (r === 'Yes') { syncPeriodInputs(); $('#reset-dayoff-form').submit(); }
        });
    });

    // Print Schedule (btnPrintSched_Click)
    $('#btnPrintSched').addEventListener('click', function () {
        if (shiftGrid.count() === 0) {
            MsgBox.show('No schedule data to print.', 'No Data', 'information');
            return;
        }
        window.open('/schedule/print?start=' + sch.start.value + '&end=' + sch.end.value, '_blank');
    });

    /* =================================================================
       PAYROLL
       ================================================================= */
    var pay = {
        form: $('#payroll-form'),
        name: $('#txtEmployeeName'), empId: $('#txtEmployeeID'), rate: $('#txtDailyRate'),
        days: $('#udDaysWorked'), ot: $('#udOTHours'), late: $('#udLate'), hol: $('#udHolidaysWork'),
        holType: $('#cmbHolidayType'),
        ded: $('#txtDeductionPerHour'), sss: $('#txtSSS'), ph: $('#txtPhilhealth'), pi: $('#txtPagibig'), oth: $('#txtOthers'),
        start: $('#dtStartPeriod'), end: $('#dtEndPeriod'), mode: $('#payMode'),
        editMode: false, selectedId: null
    };
    var role = $('#main-window').getAttribute('data-role');

    var payrollGrid = Grid($('#payrollGrid'), function (row) { // payrollGrid_CellClick
        if (!row) { return; }
        fillPayroll(row);
        loadShiftInfo(row.shift);
        calculatePayroll();
        pay.editMode = false;
        setPayrollReadOnly(true);
        setMode(pay.mode, 'view', 'Viewing ' + row.employee_id);
    });

    function fillPayroll(row) {
        pay.selectedId = row.id;
        pay.name.value = row.name; pay.empId.value = row.employee_id; pay.rate.value = row.daily_rate;
        pay.days.value = row.days_worked; pay.ot.value = row.ot_hours; pay.late.value = row.late_hours;
        pay.hol.value = row.holiday_present; pay.holType.value = row.holiday_type || '';
        pay.ded.value = row.deduction_per_hour; pay.sss.value = row.sss; pay.ph.value = row.philhealth;
        pay.pi.value = row.pagibig; pay.oth.value = row.others;
        clearInvalid(pay.form);
    }
    function loadShiftInfo(shift) { // LoadShiftInfoForEmployee
        sch.schedule.value = shift ? shift.schedule : '';
        sch.brk.value = shift ? shift.breaks : '';
        sch.duties.value = shift ? shift.duties : '';
        sch.dayoff.value = shift ? (shift.day_off || '') : '';
    }
    function setPayrollReadOnly(ro) { // SetPayrollFieldsReadOnly
        if (role !== 'admin') { ro = true; } // employees can only view their payroll
        [pay.name, pay.empId, pay.rate, pay.ded, pay.sss, pay.ph, pay.pi, pay.oth].forEach(function (i) { i.readOnly = ro; });
        [pay.days, pay.ot, pay.late, pay.hol].forEach(function (i) { setNumEnabled(i, !ro); });
        pay.holType.disabled = ro;
    }
    function calculatePayroll() { // CalculatePayroll
        var num = function (el) { return isNumber(el.value) ? Number(el.value) : 0; };
        var dailyRate = num(pay.rate) > 0 ? num(pay.rate) : Number(pay.rate.getAttribute('data-default')) || 550;
        var hourlyRate = dailyRate / 8;
        var basicPay = num(pay.days) * dailyRate;
        var holidayExtraPay = num(pay.hol) * dailyRate;
        var otPay = num(pay.ot) * (hourlyRate * 1.25);
        var lateDeduction = num(pay.late) * num(pay.ded);
        var totalDeductions = num(pay.sss) + num(pay.ph) + num(pay.pi) + num(pay.oth) + lateDeduction;
        var grossPay = basicPay;
        var netPay = grossPay + holidayExtraPay + otPay - totalDeductions;
        $('#lbEmployeeName').textContent = pay.name.value.trim() || 'New employee';
        $('#lbEmployeeID').textContent = (pay.empId.value.trim() ? pay.empId.value.trim() + ' · ' : '') +
            peso(dailyRate) + '/day · OT ' + peso(otPay) + ' · Holiday ' + peso(holidayExtraPay);
        $('#lbGrossPay').textContent = peso(grossPay);
        $('#lbTotalDeductions').textContent = peso(totalDeductions);
        $('#lbNetPay').textContent = peso(netPay);
    }
    // Live preview while typing (same formula as Add | Calculate)
    pay.form.addEventListener('input', function (e) {
        if (e.target.closest('.card') && e.target.id !== 'dtStartPeriod' && e.target.id !== 'dtEndPeriod') { calculatePayroll(); }
    });

    function validatePayroll() {
        clearInvalid(pay.form);
        if (pay.name.value.trim() === '' || pay.empId.value.trim() === '') {
            [pay.name, pay.empId].forEach(function (i) { if (i.value.trim() === '') { markInvalid(i); } });
            MsgBox.show('Employee Name and ID are required.', 'Input Error', 'warning');
            return false;
        }
        if (!isNumber(pay.rate.value) || Number(pay.rate.value) <= 0) {
            markInvalid(pay.rate);
            MsgBox.show('Daily rate must be a number greater than 0.', 'Input Error', 'warning');
            return false;
        }
        var bad = [pay.ded, pay.sss, pay.ph, pay.pi, pay.oth].filter(function (i) { return !isNumber(i.value) || Number(i.value) < 0; });
        if (bad.length) {
            bad.forEach(markInvalid);
            MsgBox.show('Please enter valid numeric values for all deduction fields.', 'Input Error', 'warning');
            return false;
        }
        return true;
    }
    function submitPayroll(action) {
        calculatePayroll();
        pay.form.action = action;
        pay.holType.disabled = false;
        [pay.days, pay.ot, pay.late, pay.hol].forEach(function (i) { i.readOnly = false; });
        pay.form.submit();
    }

    // Add | Calculate (btnAddCalculate_Click_1)
    pay.form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (role !== 'admin') { return; }
        if (!validatePayroll()) { return; }
        submitPayroll('/payroll');
    });

    // Edit (btnEditPayroll_Click)
    on('#btnEditPayroll', 'click', function () {
        if (pay.empId.value.trim() === '') {
            MsgBox.show('Please select an employee from the payroll grid first.', 'No Selection', 'warning');
            return;
        }
        pay.editMode = true;
        setPayrollReadOnly(false);
        setMode(pay.mode, 'edit', 'Editing ' + pay.empId.value.trim());
        toast("Make your changes and click 'Update' to save.", 'Edit mode enabled');
        pay.name.focus();
    });

    // Update (btnUpdatePayroll_Click)
    on('#btnUpdatePayroll', 'click', function () {
        if (!pay.editMode) {
            MsgBox.show("Please click 'Edit' first to enable editing.", 'Edit Required', 'warning');
            return;
        }
        if (!validatePayroll()) { return; }
        if (!pay.selectedId) {
            MsgBox.show('No record found to update. Employee may have been deleted.', 'Update Failed', 'warning');
            return;
        }
        submitPayroll('/payroll/' + pay.selectedId);
    });

    // Clear (btnClearPayroll_Click -> ClearPayrollFields)
    on('#btnClearPayroll', 'click', function () {
        pay.name.value = ''; pay.empId.value = ''; pay.rate.value = pay.rate.getAttribute('data-default');
        [pay.days, pay.ot, pay.late, pay.hol].forEach(function (i) { i.value = '0'; });
        pay.ded.value = ''; pay.holType.value = '';
        pay.sss.value = ''; pay.ph.value = ''; pay.pi.value = ''; pay.oth.value = '';
        sch.schedule.value = ''; sch.brk.value = ''; sch.duties.value = ''; sch.dayoff.value = '';
        pay.editMode = false;
        pay.selectedId = null;
        setPayrollReadOnly(false);
        clearInvalid(pay.form);
        payrollGrid.clear();
        setMode(pay.mode, 'new', 'New entry');
        $('#lbEmployeeName').textContent = 'No employee selected';
        $('#lbEmployeeID').textContent = 'Select a row or fill in the form';
        ['#lbGrossPay', '#lbTotalDeductions', '#lbNetPay'].forEach(function (s) { $(s).textContent = peso(0); });
        pay.name.focus();
    });

    // Print Employee Pay Summary
    $('#btnPrintEmpPaySum').addEventListener('click', function () {
        if (pay.empId.value.trim() === '') {
            MsgBox.show('Please select an employee first.', 'No Selection', 'warning');
            return;
        }
        window.open('/payroll/summary?employee_id=' + encodeURIComponent(pay.empId.value.trim()) +
            '&start=' + pay.start.value + '&end=' + pay.end.value, '_blank');
    });

    // Print Payroll
    on('#btnPrintPayroll', 'click', function () {
        if (payrollGrid.count() === 0) {
            MsgBox.show('No payroll data to print.', 'No Data', 'information');
            return;
        }
        window.open('/payroll/print?start=' + pay.start.value + '&end=' + pay.end.value, '_blank');
    });

    [['pay_start', pay.start], ['pay_end', pay.end]].forEach(function (pair) {
        var saved = store.get(pair[0]);
        if (saved && !new URLSearchParams(window.location.search).get(pair[0])) { pair[1].value = saved; }
        pair[1].addEventListener('change', function () { store.set(pair[0], pair[1].value); loadHolidays(); });
    });

    // Re-select the saved employee after Add & calculate / Update (summary stays filled, as in C#)
    var paySel = state.getAttribute('data-payroll-selected');
    // Employees: their own record is always shown in the summary.
    var payOwn = state.getAttribute('data-payroll-own');
    if (payOwn) {
        var own = payrollGrid.find('data-employee-id', payOwn);
        if (own) { payrollGrid.select(own, true); setMode(pay.mode, 'view', 'My record'); }
        else { setPayrollReadOnly(true); setMode(pay.mode, 'view', 'No record yet'); }
    }
    if (paySel && !payOwn) {
        var ptr = payrollGrid.find('data-employee-id', paySel);
        if (ptr) { payrollGrid.select(ptr, true); ptr.scrollIntoView({ block: 'nearest' }); }
        try { history.replaceState(null, '', window.location.pathname); } catch (e) { /* ignore */ }
    }
    if (old.payroll) {
        var p = old.payroll;
        pay.name.value = p.name || ''; pay.empId.value = p.employee_id || '';
        pay.days.value = p.days_worked || 0; pay.ot.value = p.ot_hours || 0; pay.late.value = p.late_hours || 0;
        pay.hol.value = p.holiday_present || 0; pay.holType.value = p.holiday_type || '';
        pay.ded.value = p.deduction_per_hour || ''; pay.sss.value = p.sss || ''; pay.ph.value = p.philhealth || '';
        pay.pi.value = p.pagibig || ''; pay.oth.value = p.others || '';
        pay.rate.value = p.daily_rate || pay.rate.getAttribute('data-default');
        var pmap = { name: pay.name, employee_id: pay.empId, daily_rate: pay.rate, deduction_per_hour: pay.ded, sss: pay.sss, philhealth: pay.ph, pagibig: pay.pi, others: pay.oth };
        Object.keys(old.errors || {}).forEach(function (k) { if (pmap[k]) { markInvalid(pmap[k]); } });
        calculatePayroll();
    }

    /* =================================================================
       Holiday banner — external Nager.Date API via /api/holidays
       ================================================================= */
    var holidayEl = $('#statusHolidays');
    function fmtDate(d) {
        return new Date(d + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    function chips(list) {
        return '<div class="chips">' + list.map(function (h) {
            return '<span class="chip"><b>' + esc(fmtDate(h.date)) + '</b> · ' + esc(h.name) + ' · ' + esc(h.type) + '</span>';
        }).join('') + '</div>';
    }
    var SRC = '<div class="src">Source: Nager.Date public holiday API</div>';
    function loadHolidays() {
        var s = pay.start.value, e = pay.end.value;
        if (!s || !e) { return; }
        holidayEl.innerHTML = '<strong>Checking Philippine holidays for this payroll period…</strong>';
        fetch('/api/holidays?start=' + s + '&end=' + e, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (!res.ok) { throw new Error(res.body.error || 'Holiday service unavailable'); }
                var list = res.body.data || [];
                if (list.length) {
                    holidayEl.innerHTML = '<strong>' + list.length + ' Philippine holiday' + (list.length > 1 ? 's' : '') +
                        ' in this payroll period</strong>' + chips(list) + SRC;
                    return;
                }
                return fetch('/api/holidays?year=' + s.slice(0, 4), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        var next = (j.data || []).filter(function (h) { return h.date > e; })[0];
                        holidayEl.innerHTML = '<strong>No Philippine holidays in this payroll period.</strong>' +
                            (next ? '<div>Next up: ' + esc(next.name) + ' on ' + esc(fmtDate(next.date)) + ' (' + esc(next.type) + ')</div>' : '') + SRC;
                    });
            })
            .catch(function (err) { holidayEl.innerHTML = '<strong>Holiday API unavailable.</strong><div>' + esc(err.message) + '</div>'; });
    }
    loadHolidays();

    showFlash();
}());
