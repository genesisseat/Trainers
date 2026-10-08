(function () {
    var pageMargin = 15;
    var pageWidth = 210;
    var pageHeight = 297;
    var contentWidth = pageWidth - pageMargin * 2;

    function pdfText(value) {
        return String(value || '')
            .replace(/[\u2018\u2019]/g, "'")
            .replace(/[\u201C\u201D]/g, '"')
            .replace(/[\u2013\u2014]/g, '-')
            .replace(/[\u2022\u00B7]/g, '-')
            .replace(/\u00A0/g, ' ')
            .normalize('NFKD')
            .replace(/[\u0300-\u036F]/g, '')
            .replace(/[^\x20-\xFF\n]/g, '?')
            .replace(/[\t ]+/g, ' ')
            .replace(/ *\n */g, '\n')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function structuredText(element) {
        var pieces = [];
        var blockElements = /^(ADDRESS|ARTICLE|ASIDE|BLOCKQUOTE|DD|DIV|DL|DT|FIELDSET|FIGCAPTION|FIGURE|FOOTER|FORM|H[1-6]|HEADER|LI|MAIN|NAV|OL|P|SECTION|TABLE|TBODY|TD|TFOOT|TH|THEAD|TR|UL)$/;

        function appendBreak() {
            if (pieces.length && pieces[pieces.length - 1] !== '\n') {
                pieces.push('\n');
            }
        }

        function visit(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                pieces.push(node.nodeValue || '');
                return;
            }
            if (node.nodeType !== Node.ELEMENT_NODE) {
                return;
            }

            var tagName = node.tagName;
            if (tagName === 'BUTTON' || tagName === 'SCRIPT' || tagName === 'STYLE') {
                return;
            }
            if (tagName === 'BR') {
                appendBreak();
                return;
            }

            var isBlock = blockElements.test(tagName);
            if (isBlock) {
                appendBreak();
            } else if (pieces.length && pieces[pieces.length - 1] !== '\n' && !/\s$/.test(pieces[pieces.length - 1])) {
                pieces.push(' ');
            }
            Array.prototype.forEach.call(node.childNodes, visit);
            if (isBlock) {
                appendBreak();
            }
        }

        visit(element);
        return pdfText(pieces.join(''));
    }

    function safeFilename(value) {
        return pdfText(value)
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'curriculum';
    }

    function createPdf() {
        var pdf = new window.jspdf.jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4', compress: true });
        pdf.setProperties({ creator: 'Curriculum Enhancer' });
        return { pdf: pdf, y: 16 };
    }

    function addText(state, text, options) {
        var pdf = state.pdf;
        var settings = options || {};
        var fontSize = settings.fontSize || 9;
        var lineHeight = fontSize * 0.48;
        var indent = settings.indent || 0;
        var lines = pdf.splitTextToSize(pdfText(text), contentWidth - indent);
        var blockHeight = Math.max(lineHeight, lines.length * lineHeight);

        if (state.y + blockHeight > pageHeight - pageMargin) {
            pdf.addPage();
            state.y = pageMargin;
        }

        pdf.setFont('helvetica', settings.bold ? 'bold' : 'normal');
        pdf.setFontSize(fontSize);
        pdf.setTextColor.apply(pdf, settings.color || [23, 32, 51]);
        pdf.text(lines, pageMargin + indent, state.y);
        state.y += blockHeight + (settings.after === undefined ? 2 : settings.after);
    }

    function ensureSpace(state, height) {
        if (state.y + height > pageHeight - pageMargin) {
            state.pdf.addPage();
            state.y = pageMargin;
        }
    }

    function addTitle(state, title, metadata) {
        addText(state, title, { fontSize: 18, bold: true, color: [30, 58, 138], after: 3 });
        (metadata || []).forEach(function (line) {
            if (line) {
                addText(state, line, { fontSize: 9, color: [76, 87, 105], after: 1 });
            }
        });
        state.y += 3;
        state.pdf.setDrawColor(216, 222, 232);
        state.pdf.line(pageMargin, state.y, pageWidth - pageMargin, state.y);
        state.y += 7;
    }

    function addHeading(state, text, level) {
        var size = level === 1 ? 14 : (level === 2 ? 11 : 9);
        ensureSpace(state, level === 1 ? 16 : 12);
        addText(state, text, { fontSize: size, bold: true, color: level === 1 ? [30, 58, 138] : [23, 32, 51], after: 3 });
    }

    function tableRows(table) {
        var head = Array.prototype.map.call(table.querySelectorAll('thead th'), function (cell) {
            return structuredText(cell);
        });
        var body = Array.prototype.map.call(table.querySelectorAll('tbody tr'), function (row) {
            return Array.prototype.map.call(row.querySelectorAll('td'), function (cell) {
                return structuredText(cell);
            });
        });
        return { head: head, body: body };
    }

    function addTable(state, table) {
        var rows = tableRows(table);
        if (!rows.head.length || !rows.body.length) {
            return;
        }
        state.pdf.autoTable({
            head: [rows.head],
            body: rows.body,
            startY: state.y,
            margin: { left: pageMargin, right: pageMargin, top: pageMargin, bottom: pageMargin + 3 },
            theme: 'grid',
            styles: { font: 'helvetica', fontSize: 8.5, cellPadding: 2.3, overflow: 'linebreak', valign: 'top', lineColor: [216, 222, 232], textColor: [23, 32, 51], lineWidth: 0.15 },
            headStyles: { fillColor: [30, 58, 138], textColor: [255, 255, 255], fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [248, 250, 252] },
            columnStyles: { 0: { cellWidth: 23 }, 2: { cellWidth: 18, halign: 'right' } },
            rowPageBreak: 'avoid'
        });
        state.y = state.pdf.lastAutoTable.finalY + 6;
    }

    function addPageFooters(state, label) {
        var pdf = state.pdf;
        var totalPages = pdf.getNumberOfPages();
        for (var page = 1; page <= totalPages; page += 1) {
            pdf.setPage(page);
            pdf.setDrawColor(216, 222, 232);
            pdf.line(pageMargin, pageHeight - 12, pageWidth - pageMargin, pageHeight - 12);
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(8);
            pdf.setTextColor(91, 101, 117);
            pdf.text(pdfText(label), pageMargin, pageHeight - 7);
            pdf.text('Page ' + page + ' of ' + totalPages, pageWidth - pageMargin, pageHeight - 7, { align: 'right' });
        }
    }

    function exportGeneratedDraft(run) {
        var state = createPdf();
        var heading = run.querySelector('.run-header h2');
        var metadata = run.querySelector('.run-meta');
        var title = heading ? structuredText(heading) : 'Generated Curriculum Draft';
        var metadataLines = metadata ? Array.prototype.map.call(metadata.children, structuredText) : [];
        addTitle(state, 'Generated Curriculum Draft', [title].concat(metadataLines));
        addText(state, 'Advisory recommendations only: verify all content before any use.', { fontSize: 9, bold: true, after: 6 });

        run.querySelectorAll('.curriculum > section').forEach(function (yearSection) {
            var yearHeading = yearSection.querySelector('.year-heading');
            if (yearHeading) {
                ensureSpace(state, 24);
                addHeading(state, structuredText(yearHeading), 1);
            }
            yearSection.querySelectorAll('.term').forEach(function (term) {
                var termHeading = term.querySelector('.term-heading strong');
                if (termHeading) {
                    ensureSpace(state, 48);
                    addHeading(state, structuredText(termHeading), 2);
                }
                var table = term.querySelector('table');
                if (table) {
                    addTable(state, table);
                }
            });
        });

        var runNumber = structuredText(run.querySelector('.draft-run-number')) || 'run';
        var filename = 'curriculum-draft-' + safeFilename(title) + '-' + safeFilename(runNumber) + '.pdf';
        addPageFooters(state, 'Curriculum Enhancer | ' + runNumber);
        state.pdf.save(filename);
    }

    function exportEnhancementReview(review) {
        var state = createPdf();
        var heading = review.querySelector('.output-header h2');
        var metadata = review.querySelector('.output-meta');
        var title = heading ? structuredText(heading) : 'Enhancement Review';
        var metadataLines = metadata ? Array.prototype.map.call(metadata.children, structuredText) : [];
        addTitle(state, 'Curriculum Enhancement Results', [title].concat(metadataLines));
        addText(state, 'Advisory recommendations only: verify all content before any use.', { fontSize: 9, bold: true, after: 6 });

        review.querySelectorAll('.review-section:not(.pdf-export-hide)').forEach(function (section) {
            var sectionHeading = section.querySelector(':scope > h3');
            if (sectionHeading) {
                addHeading(state, structuredText(sectionHeading), 1);
            }

            if (section.classList.contains('enhanced-curriculum-output')) {
                section.querySelectorAll('.enhanced-year').forEach(function (year) {
                    var yearHeading = year.querySelector('h4');
                    if (yearHeading) {
                        ensureSpace(state, 24);
                        addHeading(state, structuredText(yearHeading), 2);
                    }
                    year.querySelectorAll('.enhanced-term').forEach(function (term) {
                        var termHeading = term.querySelector('h5');
                        if (termHeading) {
                            ensureSpace(state, 48);
                            addHeading(state, structuredText(termHeading), 3);
                        }
                        var table = term.querySelector('table');
                        if (table) {
                            addTable(state, table);
                        }
                    });
                });
                return;
            }

            var summary = section.querySelector('.review-summary');
            if (summary) {
                addText(state, structuredText(summary), { fontSize: 9.5, after: 5 });
            }
            section.querySelectorAll('.review-list .review-item').forEach(function (item) {
                var subjectTitle = item.querySelector('strong');
                var status = item.querySelector('.status-badge');
                if (subjectTitle) {
                    addText(state, structuredText(subjectTitle) + (status ? ' - ' + structuredText(status) : ''), { fontSize: 9, bold: true, after: 2 });
                }
                item.querySelectorAll('p').forEach(function (paragraph) {
                    addText(state, structuredText(paragraph), { fontSize: 8.5, indent: 3, after: 2 });
                });
                state.y += 2;
            });
            section.querySelectorAll('.recommendation-list > li').forEach(function (item) {
                var recommendationContent = item.cloneNode(true);
                var postingContent = recommendationContent.querySelector('.job-postings');
                if (postingContent) {
                    postingContent.remove();
                }
                addText(state, '- ' + structuredText(recommendationContent), { fontSize: 8.5, indent: 2, after: 2 });
                var savedPostings = item.querySelector('.job-postings');
                if (savedPostings) {
                    addText(state, structuredText(savedPostings), { fontSize: 8, indent: 5, after: 4 });
                } else {
                    state.y += 2;
                }
            });
        });

        var runNumber = structuredText(review.querySelector('.draft-run-number')) || 'run';
        var filename = 'enhancement-results-' + safeFilename(title) + '-' + safeFilename(runNumber) + '.pdf';
        addPageFooters(state, 'Curriculum Enhancer | ' + runNumber);
        state.pdf.save(filename);
    }

    document.querySelectorAll('[data-export-pdf]').forEach(function (button) {
        button.addEventListener('click', function () {
            var item = button.closest('.draft-item');
            if (!item) {
                return;
            }
            var originalLabel = button.textContent;
            button.disabled = true;
            button.textContent = 'Preparing PDF...';
            window.setTimeout(function () {
                try {
                    if (!window.jspdf || !window.jspdf.jsPDF) {
                        throw new Error('The PDF export library could not be loaded.');
                    }
                    if (item.classList.contains('enhancement-history-item')) {
                        exportEnhancementReview(item);
                    } else {
                        exportGeneratedDraft(item);
                    }
                } catch (error) {
                    window.alert(error.message || 'The PDF could not be generated.');
                } finally {
                    button.disabled = false;
                    button.textContent = originalLabel;
                }
            }, 0);
        });
    });
}());