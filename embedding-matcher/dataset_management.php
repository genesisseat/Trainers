<?php
require_once __DIR__ . '/auth.php';
require_role('super_admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dataset Management</title>
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body>
    <main class="container admin-container">
        <?php require __DIR__ . '/partials/header.php'; ?>
        <h1>Dataset Management</h1>
        <p class="summary">Review the current source datasets and prepare files for a later import workflow.</p>

        <section class="panel dataset-panel" aria-labelledby="active-datasets-title">
            <div class="panel-header">
                <h2 id="active-datasets-title">Current source datasets</h2>
            </div>
            <div class="table-wrap">
                <table class="dataset-table">
                    <thead><tr><th>Dataset</th><th>Active source</th><th>Format</th><th>Use</th></tr></thead>
                    <tbody>
                        <tr><td>Curriculum courses</td><td><code>../curriculum-generator-kb/data/curriculum_dataset_with_ids.csv</code></td><td>CSV</td><td>Course evidence for matching and curriculum generation</td></tr>
                        <tr><td>Industry skills</td><td><code>../curriculum-generator-kb/03_industry_skills_data.md</code></td><td>Markdown</td><td>Industry skill source for matching and coverage analysis</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel dataset-panel" aria-labelledby="prepare-dataset-title">
            <div class="panel-header">
                <h2 id="prepare-dataset-title">Prepare a dataset file</h2>
            </div>
            <div class="panel-body">
                <div class="notice info" role="status">UI preview only. Selected files stay in this browser and are not uploaded, saved, or activated.</div>
                <div class="dataset-upload-controls">
                    <div class="field">
                        <label for="dataset-type">Dataset type</label>
                        <select id="dataset-type">
                            <option value="courses">Curriculum courses (CSV)</option>
                            <option value="skills">Industry skills (CSV or Markdown)</option>
                        </select>
                    </div>
                    <div class="field dataset-file-field">
                        <label for="dataset-file">Choose a file</label>
                        <input id="dataset-file" type="file" accept=".csv,.md,text/csv,text/markdown">
                    </div>
                    <button id="stage-dataset" type="button" disabled>Preview selection</button>
                </div>
                <p class="dataset-selection-message meta" id="dataset-selection-message" aria-live="polite">Choose a file to preview it here.</p>
                <div class="dataset-queue-wrap">
                    <h3>Preview queue</h3>
                    <div class="table-wrap">
                        <table class="dataset-table">
                            <thead><tr><th>File</th><th>Dataset type</th><th>Size</th><th>State</th></tr></thead>
                            <tbody id="dataset-queue"><tr class="dataset-queue-empty"><td colspan="4" class="meta">No files selected.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script>
        var datasetType = document.getElementById('dataset-type');
        var datasetFile = document.getElementById('dataset-file');
        var stageDataset = document.getElementById('stage-dataset');
        var selectionMessage = document.getElementById('dataset-selection-message');
        var datasetQueue = document.getElementById('dataset-queue');
        var selectedDatasetFile = null;

        function formatFileSize(bytes) {
            if (bytes < 1024 * 1024) {
                return (bytes / 1024).toFixed(1) + ' KB';
            }
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        function validateDatasetFile(file) {
            var extension = file.name.split('.').pop().toLowerCase();
            return datasetType.value === 'courses'
                ? extension === 'csv'
                : extension === 'csv' || extension === 'md';
        }

        datasetFile.addEventListener('change', function () {
            selectedDatasetFile = datasetFile.files[0] || null;
            stageDataset.disabled = !selectedDatasetFile;
            if (!selectedDatasetFile) {
                selectionMessage.textContent = 'Choose a file to preview it here.';
                return;
            }
            if (!validateDatasetFile(selectedDatasetFile)) {
                selectionMessage.textContent = datasetType.value === 'courses'
                    ? 'Curriculum course datasets must be CSV files.'
                    : 'Industry skill datasets must be CSV or Markdown files.';
                stageDataset.disabled = true;
                return;
            }
            selectionMessage.textContent = selectedDatasetFile.name + ' · ' + formatFileSize(selectedDatasetFile.size) + ' · preview only';
        });

        datasetType.addEventListener('change', function () {
            datasetFile.dispatchEvent(new Event('change'));
        });

        stageDataset.addEventListener('click', function () {
            if (!selectedDatasetFile || !validateDatasetFile(selectedDatasetFile)) {
                return;
            }
            var emptyRow = datasetQueue.querySelector('.dataset-queue-empty');
            if (emptyRow) {
                emptyRow.remove();
            }
            var row = document.createElement('tr');
            [
                selectedDatasetFile.name,
                datasetType.options[datasetType.selectedIndex].text,
                formatFileSize(selectedDatasetFile.size),
                'Preview only'
            ].forEach(function (value) {
                var cell = document.createElement('td');
                cell.textContent = value;
                row.appendChild(cell);
            });
            datasetQueue.appendChild(row);
            datasetFile.value = '';
            selectedDatasetFile = null;
            stageDataset.disabled = true;
            selectionMessage.textContent = 'Preview added for this page session only. No file was uploaded or saved.';
        });
    </script>
</body>
</html>