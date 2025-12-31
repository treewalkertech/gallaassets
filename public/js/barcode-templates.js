document.addEventListener('DOMContentLoaded', function () {

    const el = document.getElementById('barcode-app');
    if (!el) return;

    const page = el.dataset.page;

    if (page === 'index') loadTemplates(el);
    if (page === 'create') renderCreateForm(el);
    if (page === 'edit') renderEditForm(el);
});

/* ===============================
   INDEX PAGE
================================ */
function loadTemplates(el) {

    el.innerHTML = '<p>Loading...</p>';

    fetch('/barcode-templates-data', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {

        if (!Array.isArray(data) || data.length === 0) {
            el.innerHTML = '<p>No barcode templates found.</p>';
            return;
        }

        let html = `
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Template</th>
                    <th>Status</th>
                    <th width="220">Actions</th>
                </tr>
            </thead>
            <tbody>
        `;

        data.forEach(t => {
            html += `
            <tr>
                <td>${escapeHtml(t.name)}</td>
                <td><code>${escapeHtml(t.template)}</code></td>
                <td>
                    <span class="badge ${t.is_active ? 'badge-success' : 'badge-secondary'}">
                        ${t.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-success"
                        onclick="toggleStatus(${t.id})">
                        Activate
                    </button>

                    <a class="btn btn-sm btn-warning"
                       href="/admin/barcode-templates/${t.id}/edit">
                        Edit
                    </a>

                    <button class="btn btn-sm btn-danger"
                        onclick="deleteTemplate(${t.id})">
                        Delete
                    </button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        el.innerHTML = html;
    })
    .catch(err => {
        console.error(err);
        el.innerHTML = '<p class="text-danger">Failed to load templates</p>';
    });
}

/* ===============================
   CREATE PAGE
================================ */
function renderCreateForm(el) {

    el.innerHTML = templateBuilderHTML('Save');

    initTemplateBuilder({
        formId: 'barcodeForm',
        submitUrl: '/barcode-templates-data',
        method: 'POST'
    });
}

/* ===============================
   EDIT PAGE
================================ */
function renderEditForm(el) {

    const id = el.dataset.id;
    if (!id) {
        el.innerHTML = '<p class="text-danger">Invalid template ID</p>';
        return;
    }

    el.innerHTML = '<p>Loading...</p>';

    fetch(`/barcode-templates/${id}`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {

        el.innerHTML = templateBuilderHTML('Update');

        initTemplateBuilder({
            formId: 'barcodeForm',
            submitUrl: `/barcode-templates/${id}`,
            method: 'PUT',
            initialName: data.name,
            initialTemplate: data.template
        });
    });
}

/* ===============================
   TEMPLATE BUILDER (SHARED)
================================ */
function templateBuilderHTML(buttonText) {
    return `
    <form id="barcodeForm">
        <div class="form-group">
            <label>Template Name</label>
            <input class="form-control" name="name" required>
        </div>

        <div class="form-group">
            <label>Template Builder</label>

            <select id="fieldSelect" class="form-control mb-2">
                <option value="">-- Select Field --</option>
                <option value="{company}">Company</option>
                <option value="{location}">Location</option>
                <option value="{logistics}">Logistics</option>
                <option value="{po}">Purchase Order</option>
                <option value="{department}">Department</option>
                <option value="{asset_id}">Asset ID</option>
                <option value="{serial}">Serial</option>
                <option value="{year}">Year</option>
                <option value="{month}">Month</option>
            </select>

            <button type="button"
                    class="btn btn-sm btn-danger mb-2"
                    id="clearTemplate">
                Clear
            </button>

            <input type="text"
                   id="templateInput"
                   name="template"
                   class="form-control"
                   readonly
                   required>
        </div>

        <p class="mt-2"><strong>Preview:</strong></p>
        <code id="preview"></code>

        <br>
        <button class="btn btn-primary">${buttonText}</button>
    </form>`;
}


/* ===============================
   BUILDER LOGIC
================================ */
function initTemplateBuilder(config) {

    const form = document.getElementById(config.formId);
    const fieldSelect = document.getElementById('fieldSelect');
    const clearBtn = document.getElementById('clearTemplate');
    const templateInput = document.getElementById('templateInput');
    const preview = document.getElementById('preview');

    let parts = [];

    if (config.initialTemplate) {
        parts = config.initialTemplate.split('/');
        templateInput.value = config.initialTemplate;
        preview.innerText = previewBarcode(config.initialTemplate);
    }

    if (config.initialName) {
        form.name.value = config.initialName;
    }

    fieldSelect.addEventListener('change', () => {
    if (!fieldSelect.value) return;

    parts.push(fieldSelect.value);
    fieldSelect.value = '';
    update();
});


    clearBtn.addEventListener('click', () => {
        parts = [];
        update();
    });

    function update() {
        templateInput.value = parts.join('/');
        preview.innerText = previewBarcode(templateInput.value);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        fetch(config.submitUrl, {
            method: config.method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                name: form.name.value,
                template: templateInput.value
            })
        })
        .then(res => {
            if (!res.ok) throw new Error('Save failed');
            return res.json();
        })
        .then(() => {
            window.location.href = '/admin/barcode-templates';
        })
        .catch(err => {
            alert(err.message);
        });
    });
}

/* ===============================
   ACTIONS
================================ */
function toggleStatus(id) {
    if (!confirm('Activate this template?')) return;

    fetch(`/barcode-templates/${id}/toggle`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    }).then(() => location.reload());
}

function deleteTemplate(id) {
    if (!confirm('Delete this template?')) return;

    fetch(`/barcode-templates/${id}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    }).then(() => location.reload());
}

/* ===============================
   PREVIEW
================================ */
function previewBarcode(template) {
    return template
        .replace('{company}', 'KWE')
        .replace('{location}', 'BOM-VADAPE')
        .replace('{logistics}', 'C&F')
        .replace('{po}', 'PO1223')
        .replace('{department}', 'DTP')
        .replace('{asset_id}', '1204')
        .replace('{serial}', 'SN12345')
        .replace('{year}', new Date().getFullYear())
        .replace('{month}', String(new Date().getMonth() + 1).padStart(2, '0'));
}

/* ===============================
   XSS SAFETY
================================ */
function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
}
