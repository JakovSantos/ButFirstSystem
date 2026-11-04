/* =====================================================================
   BUT FIRST, SYSTEM - client behaviour
   Port of the C# event handlers in Form1.cs / Form2.cs. The server (PHP)
   re-validates everything; this file only reproduces the desktop UX:
   MessageBoxes, grid CellClick, edit/lock modes and button rules.
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
    function n2(v) { return Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    /* -----------------------------------------------------------------
       MessageBox.Show(text, caption, buttons, icon)
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
                iconEl.className = 'msgbox-icon' + (icon ? ' icon-' + icon : '');
                var box = $('#msgbox-buttons');
                box.innerHTML = '';
                var lastFocus = document.activeElement;

                function done(result) {
                    overlay.hidden = true;
                    document.removeEventListener('keydown', onKey, true);
                    if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) { /* ignore */ } }
                    resolve(result);
                }
                function onKey(e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        done(buttons.indexOf('No') >= 0 ? 'No' : buttons[buttons.length - 1]);
                    }
                }

                buttons.forEach(function (label, i) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.textContent = label;
                    if (i === 0) { b.className = 'default'; }
                    b.addEventListener('click', function () { done(label); });
                    box.appendChild(b);
                });
                overlay.querySelector('[data-msgbox="close"]').onclick = function () {
                    done(buttons.indexOf('No') >= 0 ? 'No' : buttons[buttons.length - 1]);
                };
                document.addEventListener('keydown', onKey, true);
                overlay.hidden = false;
                box.firstChild.focus();
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

    /* -----------------------------------------------------------------
       Window chrome: fit-to-screen, minimize, maximize, close
       ----------------------------------------------------------------- */
    var win = $('.win');
    var maximized = false;

    function fit() {
        if (!win) { return; }
        var w = win.offsetWidth, h = win.offsetHeight;
        var availW = window.innerWidth - 32, availH = window.innerHeight - 48;
        var scale = maximized ? Math.min(availW / w, availH / h) : Math.min(1, availW / w);
        win.style.zoom = scale > 0 ? String(scale) : '1';
        win.classList.toggle('maximized', maximized);
    }
    window.addEventListener('resize', fit);
    fit();

    $$('[data-window]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-window');
            if (action === 'maximize') { maximized = !maximized; fit(); return; }
            if (action === 'minimize') { minimize(); return; }
            if (action === 'close') {
                if (win.getAttribute('data-window-kind') === 'main') { $('#logout-form').submit(); }
                else { exitApp(); }
            }
        });
    });

    function minimize() {
        win.hidden = true;
        var bar = document.createElement('button');
        bar.type = 'button';
        bar.className = 'wf-btn-std';
        bar.style.cssText = 'position:fixed;left:16px;bottom:16px;height:32px;padding:0 14px;';
        bar.innerHTML = '<img src="/favicon.ico" alt="" width="16" height="16" style="margin-right:8px"> But First, System';
        bar.addEventListener('click', function () { win.hidden = false; bar.remove(); fit(); });
        document.body.appendChild(bar);
    }

    function exitApp() { // Application.Exit()
        window.close();
        setTimeout(function () {
            if (win) { win.hidden = true; }
            var note = $('#closed-note');
            if (note) { note.hidden = false; }
        }, 50);
    }

    /* -----------------------------------------------------------------
       Flash message from the server (MessageBox after a redirect)
       ----------------------------------------------------------------- */
    function showFlash() {
        var f = $('#flash');
        if (!f) { return Promise.resolve(); }
        return MsgBox.show(f.getAttribute('data-message'), f.getAttribute('data-caption'), f.getAttribute('data-icon'));
    }

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
        $('#btnExit').addEventListener('click', exitApp);
        showFlash().then(function () {
            var f = $('#flash');
            if (f && f.getAttribute('data-caption') === 'Login Failed') {
                txtPassword.value = '';
                txtPassword.focus();
            }
        });
        return; // nothing else on the login page
    }

    if (!$('#main-window')) { showFlash(); return; }

    /* -----------------------------------------------------------------
       MAIN WINDOW (Form2)
       ----------------------------------------------------------------- */
    var old = parseJson(($('#old-input') || {}).getAttribute ? $('#old-input').getAttribute('data-json') : '{}', {}) || {};
    var state = $('#page-state');

    /* ---------- TabControl ---------- */
    $$('.wf-tab').forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            selectTab(tab.getAttribute('data-tab'));
        });
    });
    function selectTab(name) {
        $$('.wf-tab').forEach(function (t) { t.classList.toggle('selected', t.getAttribute('data-tab') === name); });
        $$('.wf-tabpage').forEach(function (p) { p.hidden = p.getAttribute('data-tabpage') !== name; });
        try { history.replaceState(null, '', '/' + name + window.location.search.replace(/([?&])(search|selected)=[^&]*/g, '$1').replace(/[?&]+$/, '')); } catch (e) { /* ignore */ }
    }

    /* ---------- NumericUpDown ---------- */
    $$('.wf-num').forEach(function (box) {
        var input = $('input', box);
        var min = Number(input.getAttribute('data-min') || 0);
        var max = Number(input.getAttribute('data-max') || 100);
        function clamp() {
            var v = parseInt(input.value, 10);
            if (isNaN(v)) { v = min; }
            input.value = String(Math.max(min, Math.min(max, v)));
        }
        $$('.wf-spin button', box).forEach(function (b) {
            b.addEventListener('mousedown', function (e) { e.preventDefault(); });
            b.addEventListener('click', function () {
                if (box.classList.contains('disabled') || input.readOnly) { return; }
                clamp();
                input.value = String(Math.max(min, Math.min(max, Number(input.value) + Number(b.getAttribute('data-step')))));
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                e.preventDefault();
                if (input.readOnly) { return; }
                clamp();
                input.value = String(Math.max(min, Math.min(max, Number(input.value) + (e.key === 'ArrowUp' ? 1 : -1))));
            } else if (e.key.length === 1 && !/[0-9]/.test(e.key) && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
            }
        });
        input.addEventListener('blur', clamp);
    });
    function setNumEnabled(input, enabled) {
        input.readOnly = !enabled;
        input.closest('.wf-num').classList.toggle('disabled', !enabled);
    }

    /* ---------- DataGridView ---------- */
    function Grid(el, onCellClick) {
        var mode = el.getAttribute('data-select'); // 'cell' (RowHeaderSelect) or 'row' (FullRowSelect)
        var rows = $$('tbody tr', el);
        var self = { el: el, current: null };

        function clear() {
            rows.forEach(function (r) { r.classList.remove('current', 'selrow'); });
            $$('td.sel', el).forEach(function (c) { c.classList.remove('sel'); });
        }
        self.select = function (tr, td, fire) {
            clear();
            tr.classList.add('current');
            if (mode === 'row') { tr.classList.add('selrow'); }
            else { (td || tr.querySelector('td')).classList.add('sel'); }
            self.current = tr.classList.contains('newrow') ? null : tr;
            if (fire && onCellClick) { onCellClick(self.current ? parseJson(tr.getAttribute('data-row'), null) : null, tr); }
        };
        self.find = function (attr, value) {
            for (var i = 0; i < rows.length; i++) { if (rows[i].getAttribute(attr) === String(value)) { return rows[i]; } }
            return null;
        };
        self.data = function () { return self.current ? parseJson(self.current.getAttribute('data-row'), null) : null; };
        self.count = function () { return rows.filter(function (r) { return !r.classList.contains('newrow'); }).length; };

        el.addEventListener('click', function (e) {
            var tr = e.target.closest('tbody tr');
            if (!tr) { return; }
            var td = e.target.closest('td');
            self.select(tr, td, true);
        });

        // A DataGridView selects its first cell when it is bound.
        if (rows.length) { self.select(rows[0], null, false); }
        return self;
    }

    /* =================================================================
       INVENTORY TAB
       ================================================================= */
    var inv = {
        form: $('#product-form'),
        name: $('#txtProductName'), category: $('#cmbCategory'), qty: $('#udQuantity'),
        price: $('#txtPrice'), date: $('#dtDateAdded'), supplier: $('#txtSupplier'),
        id: $('#txtProductID'), path: $('#txtFilePath'), hidPath: $('#hidImagePath'),
        file: $('#fileImage'), pbox: $('#pboxProductImage'), btnAdd: $('#btnAdd'),
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
    });

    function showImage(url) {
        var img = $('img', inv.pbox);
        if (url) { img.src = url; img.hidden = false; } else { img.removeAttribute('src'); img.hidden = true; }
    }
    function fillProduct(row) {
        inv.id.value = row.product_id;
        inv.name.value = row.product_name;
        inv.category.value = row.category;
        inv.price.value = row.price;
        inv.qty.value = row.quantity;
        inv.supplier.value = row.supplier || '';
        if (row.date_added) { inv.date.value = row.date_added; }
        inv.path.value = row.image_path || '';
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
        inv.path.readOnly = true;
    }
    function clearFields(keepSearch) { // ClearFields
        inv.id.value = ''; inv.name.value = ''; inv.category.value = ''; inv.price.value = '';
        inv.qty.value = '0'; inv.supplier.value = ''; inv.date.value = today;
        inv.path.value = ''; inv.hidPath.value = ''; inv.file.value = '';
        showImage('');
        if (!keepSearch) { $('#txtSearch').value = ''; }
        inv.btnAdd.textContent = 'Add';
        inv.editProductId = -1;
        inv.isEditMode = false;
        lockProductFields(false);
        clearInvalid(inv.form);
    }
    function clearInvalid(root) { $$('.invalid', root).forEach(function (el) { el.classList.remove('invalid'); }); }
    function markInvalid(el) { (el.closest('.wf-num') || el).classList.add('invalid'); }

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
        inv.btnAdd.textContent = 'Update';
    });

    // Remove (btnRemove_Click)
    $('#btnRemove').addEventListener('click', function () {
        var row = inventoryGrid.data();
        if (!row) {
            MsgBox.show('Please select a product to remove.', 'No Selection', 'warning');
            return;
        }
        MsgBox.show('Are you sure you want to remove this product?', 'Confirm Delete', 'warning', ['Yes', 'No']).then(function (r) {
            if (r !== 'Yes') { return; }
            var f = $('#remove-form');
            f.action = '/inventory/' + row.product_id + '/delete';
            f.submit();
        });
    });

    // Browse Image (btnBrowse_Click)
    $('#btnBrowse').addEventListener('click', function () {
        if (inv.editProductId !== -1 && !inv.isEditMode) {
            MsgBox.show("Please click 'Edit' before browsing for an image.", 'Edit Mode Required', 'information');
            return;
        }
        inv.file.click();
    });
    inv.file.addEventListener('change', function () {
        var f = inv.file.files && inv.file.files[0];
        if (!f) { return; }
        if (!/\.(jpe?g|png|bmp)$/i.test(f.name)) {
            inv.file.value = '';
            MsgBox.show('Image Files (*.jpg; *.jpeg; *.png; *.bmp) only.', 'Select an Image', 'warning');
            return;
        }
        inv.path.value = f.name;
        showImage(URL.createObjectURL(f));
    });

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
    if (search.value !== '' && !$('#tab-inventory').hidden) {
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

    // After add/update the C# app calls ClearFields(); we only move the grid's current row.
    var invSel = state.getAttribute('data-inventory-selected');
    if (invSel) {
        var tr = inventoryGrid.find('data-id', invSel);
        if (tr) { inventoryGrid.select(tr, null, false); tr.scrollIntoView({ block: 'nearest' }); }
        try { history.replaceState(null, '', window.location.pathname); } catch (e) { /* ignore */ }
    }

    // Re-fill after a server-side validation error
    if (old.inventory) {
        var o = old.inventory;
        inv.name.value = o.product_name || ''; inv.category.value = o.category || ''; inv.price.value = o.price || '';
        inv.qty.value = o.quantity || 0; inv.supplier.value = o.supplier || ''; inv.date.value = o.date_added || today;
        inv.hidPath.value = o.image_path || ''; inv.path.value = o.image_path || '';
        if (old.editing) { inv.editProductId = old.editing; inv.isEditMode = true; inv.id.value = old.editing; inv.btnAdd.textContent = 'Update'; }
        var map = { product_name: inv.name, category: inv.category, price: inv.price, quantity: inv.qty, date_added: inv.date };
        Object.keys(old.errors || {}).forEach(function (k) { if (map[k]) { markInvalid(map[k]); } });
    }

    /* =================================================================
       EMPLOYEE SCHEDULE TAB
       ================================================================= */
    var sch = {
        employee: $('#txtEmployee'), schedule: $('#txtSchedule'), brk: $('#txtBreak'),
        duties: $('#txtDuties'), dayoff: $('#cmbDayoff'), start: $('#dtStartDate'), end: $('#dtEndDate')
    };

    var shiftGrid = Grid($('#shiftGrid'), function (row) { // shiftGrid_CellClick
        if (!row) { return; }
        sch.employee.value = row.names;
        sch.schedule.value = row.schedule;
        sch.brk.value = row.breaks;
        sch.duties.value = row.duties;
        sch.dayoff.value = row.day_off || '';
    });

    // Remember the period pickers (DateTimePicker keeps its value while the form is open)
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

    // Request (btnRequest_Click)
    $('#dayoff-form').addEventListener('submit', function (e) {
        if (sch.employee.value.trim() === '') {
            e.preventDefault();
            MsgBox.show('Please select an employee from the grid.', 'Validation Error', 'warning');
            return;
        }
        if (sch.dayoff.value === '') {
            e.preventDefault();
            MsgBox.show('Please select a day off from the dropdown.', 'Validation Error', 'warning');
            return;
        }
        $('#hidDayoffName').value = sch.employee.value.trim();
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
       PAYROLL MANAGEMENT TAB
       ================================================================= */
    var pay = {
        form: $('#payroll-form'),
        name: $('#txtEmployeeName'), empId: $('#txtEmployeeID'),
        days: $('#udDaysWorked'), ot: $('#udOTHours'), late: $('#udLate'), hol: $('#udHolidaysWork'),
        holType: $('#cmbHolidayType'),
        ded: $('#txtDeductionPerHour'), sss: $('#txtSSS'), ph: $('#txtPhilhealth'), pi: $('#txtPagibig'), oth: $('#txtOthers'),
        start: $('#dtStartPeriod'), end: $('#dtEndPeriod'),
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
    });

    function fillPayroll(row) {
        pay.selectedId = row.id;
        pay.name.value = row.name; pay.empId.value = row.employee_id;
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
        [pay.name, pay.empId, pay.ded, pay.sss, pay.ph, pay.pi, pay.oth].forEach(function (i) { i.readOnly = ro; });
        [pay.days, pay.ot, pay.late, pay.hol].forEach(function (i) { setNumEnabled(i, !ro); });
        pay.holType.disabled = ro;
    }
    function calculatePayroll() { // CalculatePayroll
        var dailyRate = 550, hourlyRate = dailyRate / 8;
        var num = function (el) { return isNumber(el.value) ? Number(el.value) : 0; };
        var basicPay = num(pay.days) * dailyRate;
        var holidayExtraPay = num(pay.hol) * dailyRate;
        var otPay = num(pay.ot) * (hourlyRate * 1.25);
        var lateDeduction = num(pay.late) * num(pay.ded);
        var totalDeductions = num(pay.sss) + num(pay.ph) + num(pay.pi) + num(pay.oth) + lateDeduction;
        var grossPay = basicPay;
        var netPay = grossPay + holidayExtraPay + otPay - totalDeductions;
        $('#lbEmployeeName').textContent = 'Name: ' + pay.name.value;
        $('#lbEmployeeID').textContent = 'Employee ID: ' + pay.empId.value;
        $('#lbGrossPay').textContent = 'Gross Pay: ' + n2(grossPay);
        $('#lbNetPay').textContent = 'Net Pay: ' + n2(netPay);
    }
    function validatePayroll() {
        clearInvalid(pay.form);
        if (pay.name.value.trim() === '' || pay.empId.value.trim() === '') {
            [pay.name, pay.empId].forEach(function (i) { if (i.value.trim() === '') { markInvalid(i); } });
            MsgBox.show('Employee Name and ID are required.', 'Input Error', 'warning');
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
    $('#btnEditPayroll').addEventListener('click', function () {
        if (pay.empId.value.trim() === '') {
            MsgBox.show('Please select an employee from the payroll grid first.', 'No Selection', 'warning');
            return;
        }
        pay.editMode = true;
        setPayrollReadOnly(false);
        MsgBox.show("Edit mode enabled. Make your changes and click 'Update Payroll' to save.", 'Edit Mode', 'information');
    });

    // Update (btnUpdatePayroll_Click)
    $('#btnUpdatePayroll').addEventListener('click', function () {
        if (!pay.editMode) {
            MsgBox.show("Please click 'Edit Payroll' first to enable editing.", 'Edit Required', 'warning');
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
    $('#btnClearPayroll').addEventListener('click', function () {
        pay.name.value = ''; pay.empId.value = '';
        [pay.days, pay.ot, pay.late, pay.hol].forEach(function (i) { i.value = '0'; });
        pay.ded.value = ''; pay.holType.value = '';
        pay.sss.value = ''; pay.ph.value = ''; pay.pi.value = ''; pay.oth.value = '';
        sch.schedule.value = ''; sch.brk.value = ''; sch.duties.value = ''; sch.dayoff.value = '';
        pay.editMode = false;
        pay.selectedId = null;
        setPayrollReadOnly(false); // ready for a new entry
        clearInvalid(pay.form);
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
    $('#btnPrintPayroll').addEventListener('click', function () {
        if (payrollGrid.count() === 0) {
            MsgBox.show('No payroll data to print.', 'No Data', 'information');
            return;
        }
        window.open('/payroll/print?start=' + pay.start.value + '&end=' + pay.end.value, '_blank');
    });

    // Payroll period pickers keep their value across reloads
    [['pay_start', pay.start], ['pay_end', pay.end]].forEach(function (pair) {
        var saved = store.get(pair[0]);
        if (saved && !new URLSearchParams(window.location.search).get(pair[0])) { pair[1].value = saved; }
        pair[1].addEventListener('change', function () { store.set(pair[0], pair[1].value); loadHolidays(); });
    });

    // Re-select the saved employee after Add | Calculate / Update (labels stay filled, as in C#)
    var paySel = state.getAttribute('data-payroll-selected');
    if (paySel) {
        var ptr = payrollGrid.find('data-employee-id', paySel);
        if (ptr) { payrollGrid.select(ptr, null, true); ptr.scrollIntoView({ block: 'nearest' }); }
        try { history.replaceState(null, '', window.location.pathname); } catch (e) { /* ignore */ }
    }
    if (old.payroll) {
        var p = old.payroll;
        pay.name.value = p.name || ''; pay.empId.value = p.employee_id || '';
        pay.days.value = p.days_worked || 0; pay.ot.value = p.ot_hours || 0; pay.late.value = p.late_hours || 0;
        pay.hol.value = p.holiday_present || 0; pay.holType.value = p.holiday_type || '';
        pay.ded.value = p.deduction_per_hour || ''; pay.sss.value = p.sss || ''; pay.ph.value = p.philhealth || '';
        pay.pi.value = p.pagibig || ''; pay.oth.value = p.others || '';
        var pmap = { name: pay.name, employee_id: pay.empId, deduction_per_hour: pay.ded, sss: pay.sss, philhealth: pay.ph, pagibig: pay.pi, others: pay.oth };
        Object.keys(old.errors || {}).forEach(function (k) { if (pmap[k]) { markInvalid(pmap[k]); } });
    }

    /* =================================================================
       STATUS STRIP: Philippine holidays (external Nager.Date API via /api/holidays)
       ================================================================= */
    var statusEl = $('#statusHolidays');
    function fmtDate(d) {
        var dt = new Date(d + 'T00:00:00');
        return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    function loadHolidays() {
        var s = pay.start.value, e = pay.end.value;
        if (!s || !e) { return; }
        statusEl.textContent = 'Checking Philippine holidays for the payroll period…';
        fetch('/api/holidays?start=' + s + '&end=' + e, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (!res.ok) { throw new Error(res.body.error || 'Holiday service unavailable'); }
                var list = res.body.data || [];
                if (list.length) {
                    statusEl.textContent = 'PH holidays in payroll period: ' + list.map(function (h) {
                        return h.name + ' (' + fmtDate(h.date) + ', ' + h.type + ')';
                    }).join('; ') + '  —  Nager.Date API';
                    $('#udHolidaysWork').closest('.wf-num').title = list.length + ' holiday(s) in this payroll period';
                    return;
                }
                return fetch('/api/holidays?year=' + s.slice(0, 4), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        var next = (j.data || []).filter(function (h) { return h.date > e; })[0];
                        statusEl.textContent = 'No PH holidays in the payroll period.' +
                            (next ? ' Next holiday: ' + next.name + ' (' + fmtDate(next.date) + ', ' + next.type + ')' : '') +
                            '  —  Nager.Date API';
                    });
            })
            .catch(function (err) { statusEl.textContent = 'Holiday API unavailable: ' + err.message; });
    }
    loadHolidays();

    showFlash();
}());
