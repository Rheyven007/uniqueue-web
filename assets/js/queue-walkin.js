// assets/js/queue-walkin.js
(function() {
    'use strict';

    const officeSelect = document.getElementById('office_id');
    const docSection = document.getElementById('document-section');
    const docList = document.getElementById('document-list');
    const priorityCheck = document.getElementById('priority-check');
    const prioritySection = document.getElementById('priority-section');

    // Handle Office Selection
    officeSelect.addEventListener('change', function() {
        const officeId = this.value;
        if (!officeId) {
            docSection.classList.add('hidden');
            return;
        }

        // Fetch documents for the selected office
        fetch(`/api/get-documents-by-office.php?office_id=${officeId}`)
            .then(res => res.json())
            .then(data => {
                docList.innerHTML = '';
                if (data.success && data.documents.length > 0) {
                    data.documents.forEach(doc => {
                        const label = document.createElement('label');
                        label.className = 'checkbox-item';
                        
                        let reqHtml = '';
                        if (doc.requirements) {
                            const reqList = doc.requirements.split('||');
                            reqHtml = `<ul class="document-requirements-preview">
                                ${reqList.map(r => `<li><small>• ${r}</small></li>`).join('')}
                            </ul>`;
                        } else {
                            reqHtml = '<p><small class="text-muted">No requirements listed.</small></p>';
                        }

                        label.innerHTML = `
                            <div class="doc-selection-wrapper">
                                <input type="checkbox" name="documents[${doc.id}]" value="1">
                                <strong>${doc.name}</strong>
                                ${reqHtml}
                            </div>
                        `;
                        docList.appendChild(label);
                    });
                    docSection.classList.remove('hidden');
                } else {
                    docSection.classList.add('hidden');
                }
            });
    });

    // Handle Priority Toggle
    priorityCheck.addEventListener('change', function() {
        if (this.checked) {
            prioritySection.classList.remove('hidden');
        } else {
            prioritySection.classList.add('hidden');
        }
    });
})();