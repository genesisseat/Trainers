(function () {
    var pageMargin = 15;
    var pageWidth = 210;
    var pageHeight = 297;
    var contentWidth = pageWidth - pageMargin * 2;
    var palette = {
        navy: [30, 58, 138],
        ink: [23, 32, 51],
        muted: [91, 101, 117],
        line: [216, 222, 232],
        soft: [248, 250, 252],
        advisory: [243, 246, 250]
    };

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
        if (!element) {
            return '';
        }
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
        pdf.setProperties({ creator: "Curri'KoToh" });
        return { pdf: pdf, y: pageMargin, tableHeaderVisible: false };
    }

    function addText(state, text, options) {
        var pdf = state.pdf;
        var settings = options || {};
        var fontSize = settings.fontSize || 9;
        var lineHeight = fontSize * 0.44;
        var indent = settings.indent || 0;
        var x = settings.x === undefined ? pageMargin : settings.x;
        var width = settings.width === undefined ? contentWidth - indent : settings.width;
        var lines = pdf.splitTextToSize(pdfText(text), Math.max(10, width));
        var blockHeight = Math.max(lineHeight, lines.length * lineHeight);

        if (state.y + blockHeight > pageHeight - pageMargin) {
            newPage(state);
        }

        pdf.setFont('helvetica', settings.bold ? 'bold' : 'normal');
        pdf.setFontSize(fontSize);
        pdf.setTextColor.apply(pdf, settings.color || palette.ink);
        pdf.text(lines, x + indent, state.y, settings.align ? { align: settings.align } : undefined);
        state.y += blockHeight + (settings.after === undefined ? 2 : settings.after);
        return blockHeight;
    }

    function ensureSpace(state, height) {
        if (state.y + height > pageHeight - pageMargin) {
            newPage(state);
        }
    }

    function newPage(state) {
        state.pdf.addPage();
        state.y = pageMargin;
        state.tableHeaderVisible = false;
    }

    function addRule(state, y) {
        state.pdf.setDrawColor.apply(state.pdf, palette.line);
        state.pdf.setLineWidth(0.2);
        state.pdf.line(pageMargin, y === undefined ? state.y : y, pageWidth - pageMargin, y === undefined ? state.y : y);
    }

    function addSectionHeading(state, text, contentHeight) {
        var heading = pdfText(text);
        if (!heading) {
            return;
        }
        ensureSpace(state, 10 + (contentHeight || 0));
        state.y += 2;
        addText(state, heading, { fontSize: 13, bold: true, color: palette.navy, after: 2 });
        addRule(state, state.y);
        state.y += 4;
    }

    function metaValue(metadata, label) {
        if (!metadata || !metadata.children) {
            return '';
        }
        var wanted = label.toLowerCase();
        for (var i = 0; i < metadata.children.length; i += 1) {
            var child = metadata.children[i];
            var strong = child.querySelector ? child.querySelector('strong') : null;
            if (!strong || structuredText(strong).replace(/:$/, '').toLowerCase() !== wanted) {
                continue;
            }
            return pdfText((child.textContent || '').replace(strong.textContent || '', '').replace(/^\s*:\s*/, ''));
        }
        return '';
    }

    function addHeader(state, documentTitle, runTitle, metadata, runNumber, prompt, fallbackMetadata) {
        var fallback = fallbackMetadata || {};
        addText(state, documentTitle, { fontSize: 18, bold: true, color: palette.navy, after: 1 });
        if (runTitle && runTitle !== documentTitle) {
            addText(state, runTitle, { fontSize: 11, bold: true, color: palette.ink, after: 3 });
        }

        var fields = [
            ['Program', metaValue(metadata, 'Program') || fallback.program || ''],
            ['Run', runNumber || metaValue(metadata, 'Run') || fallback.run || ''],
            ['Generated', metaValue(metadata, 'Generated') || fallback.generated || ''],
            ['Updated', metaValue(metadata, 'Updated') || fallback.updated || ''],
            ['Created by', metaValue(metadata, 'Created by') || fallback.createdBy || ''],
            ['Generation', metaValue(metadata, 'Generation') || fallback.generation || '']
        ];
        var presentFields = fields.filter(function (field) { return field[1]; });
        for (var fieldIndex = 0; fieldIndex < presentFields.length; fieldIndex += 2) {
            var metaLine = presentFields.slice(fieldIndex, fieldIndex + 2).map(function (field) {
                return field[0] + ': ' + field[1];
            }).join('   |   ');
            addText(state, metaLine, { fontSize: 8.2, color: palette.muted, after: 1 });
        }
        if (presentFields.length) {
            state.y += 2;
        }

        if (prompt) {
            addText(state, 'Prompt: ' + prompt, { fontSize: 7.5, color: palette.muted, after: 3 });
        }
        var advisoryHeight = 8;
        ensureSpace(state, advisoryHeight + 4);
        var boxY = state.y - 1;
        state.pdf.setFillColor.apply(state.pdf, palette.advisory);
        state.pdf.setDrawColor.apply(state.pdf, palette.line);
        state.pdf.roundedRect(pageMargin, boxY, contentWidth, advisoryHeight, 1.5, 1.5, 'FD');
        state.y = boxY + 5;
        addText(state, 'Advisory recommendations only: verify all content before any use.', {
            x: pageMargin + 3, width: contentWidth - 6, fontSize: 8.2, bold: true, color: palette.muted, after: 0
        });
        state.y = boxY + advisoryHeight + 3;
        addRule(state);
        state.y += 5;
    }

    function elementTextWithLinks(element) {
        if (!element) {
            return '';
        }
        var text = structuredText(element);
        var links = element.querySelectorAll ? Array.prototype.map.call(element.querySelectorAll('a[href]'), function (anchor) {
            var label = pdfText(anchor.textContent || '');
            var href = pdfText(anchor.href || anchor.getAttribute('href') || '');
            return href ? (label ? label + ': ' : '') + href : '';
        }).filter(Boolean) : [];
        return text + (links.length ? ' [' + links.join('; ') + ']' : '');
    }

    function labeledValue(element) {
        var labelNode = element && element.querySelector ? element.querySelector('strong') : null;
        var valueNode = element && element.querySelector ? element.querySelector('span') : null;
        if (!labelNode) {
            return { label: '', value: elementTextWithLinks(element) };
        }
        var label = pdfText(labelNode.textContent || '').replace(/:$/, '');
        var value = valueNode ? elementTextWithLinks(valueNode) : pdfText((element.textContent || '').replace(labelNode.textContent || '', ''));
        return { label: label, value: value };
    }

    function courseDetails(row) {
        var cells = row.querySelectorAll('td');
        var titleCell = cells[1];
        if (!titleCell) {
            return { title: '', details: [] };
        }
        var titleNode = titleCell.querySelector('.course-title') || titleCell.querySelector('strong');
        var title = titleNode ? structuredText(titleNode) : '';
        var details = [];
        var description = titleCell.querySelector('.course-description:not(.course-description-labeled)');
        if (description && structuredText(description)) {
            details.push({ label: 'Description', value: elementTextWithLinks(description) });
        }
        var topicList = titleCell.querySelector('.course-topics');
        if (topicList) {
            var topics = Array.prototype.map.call(topicList.querySelectorAll('li'), structuredText).filter(Boolean);
            if (topics.length) {
                details.push({ label: 'Topics', value: topics.join('; ') });
            }
        }
        Array.prototype.forEach.call(titleCell.querySelectorAll('.course-description-labeled'), function (item) {
            var detail = labeledValue(item);
            var label = detail.label.toLowerCase();
            if (!detail.value || (label === 'prerequisite' && detail.value.trim().toLowerCase() === 'none')) {
                return;
            }
            if (label === 'recommended tools/apps') {
                var lines = detail.value.split(/\n+/).map(function (line) { return line.trim(); }).filter(Boolean);
                lines.forEach(function (line) {
                    details.push({ label: detail.label, value: line });
                });
            } else {
                details.push(detail);
            }
        });
        Array.prototype.forEach.call(titleCell.querySelectorAll('.course-meta > span'), function (item) {
            var detail = labeledValue(item);
            var normalized = detail.value.trim().toLowerCase();
            if (detail.label && detail.value && !(detail.label.toLowerCase() === 'prerequisite' && normalized === 'none')) {
                details.push(detail);
            }
        });
        return { title: title, details: details };
    }

    function tableColumnWidths() {
        return [36, contentWidth - 36 - 16, 16];
    }

    function courseDetailHeight(state, detail, width, comfortableSpacing) {
        if (!comfortableSpacing) {
            var regularFontSize = detail.label === 'Description' || detail.label === 'Topics' || detail.label === 'Why it is taught' ? 7.5 : 7.1;
            state.pdf.setFont('helvetica', 'normal');
            state.pdf.setFontSize(regularFontSize);
            var regularText = detail.label ? detail.label + ': ' + detail.value : detail.value;
            return Math.max(3.4, state.pdf.splitTextToSize(pdfText(regularText), width).length * regularFontSize * 0.44) + 0.8;
        }
        var value = detail.value || '';
        var label = detail.label ? detail.label + ': ' : '';
        state.pdf.setFont('helvetica', 'normal');
        state.pdf.setFontSize(7.8);
        var labelWidth = 0;
        if (label) {
            state.pdf.setFont('helvetica', 'bold');
            labelWidth = state.pdf.getTextWidth(label);
            state.pdf.setFont('helvetica', 'normal');
        }
        var firstLineWidth = Math.max(12, width - labelWidth);
        var lines = state.pdf.splitTextToSize(pdfText(value), firstLineWidth);
        if (lines.length > 1) {
            lines = lines.slice(0, 1).concat(state.pdf.splitTextToSize(lines.slice(1).join(' '), width));
        }
        return Math.max(3.9, lines.length * 3.9) + 1.8;
    }

    function drawCourseDetail(state, detail, x, width, comfortableSpacing) {
        if (!comfortableSpacing) {
            var regularColor = detail.label === 'Description' || detail.label === 'Topics' || detail.label === 'Why it is taught'
                ? palette.ink
                : palette.muted;
            addText(state, detail.label ? detail.label + ': ' + detail.value : detail.value, {
                x: x,
                width: width,
                fontSize: detail.label === 'Description' || detail.label === 'Topics' || detail.label === 'Why it is taught' ? 7.5 : 7.1,
                color: regularColor,
                after: 0.8
            });
            return;
        }
        var pdf = state.pdf;
        var label = detail.label ? detail.label + ': ' : '';
        var fontSize = 7.8;
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(fontSize);
        var labelWidth = label ? pdf.getTextWidth(label) : 0;
        pdf.setFont('helvetica', 'normal');
        var valueLines = pdf.splitTextToSize(pdfText(detail.value || ''), Math.max(12, width - labelWidth));
        if (valueLines.length > 1) {
            valueLines = valueLines.slice(0, 1).concat(pdf.splitTextToSize(valueLines.slice(1).join(' '), width));
        }
        pdf.setFont('helvetica', 'bold');
        pdf.setTextColor.apply(pdf, palette.muted);
        if (label) {
            pdf.text(label, x, state.y);
        }
        pdf.setFont('helvetica', 'normal');
        pdf.setTextColor.apply(pdf, detail.label === 'Description' || detail.label === 'Topics' || detail.label === 'Why it is taught'
            ? palette.ink
            : palette.muted);
        if (valueLines.length) {
            pdf.text(valueLines[0], x + labelWidth, state.y);
            if (valueLines.length > 1) {
                pdf.text(valueLines.slice(1), x, state.y + 3.9);
            }
        }
        state.y += Math.max(3.9, valueLines.length * 3.9) + 1.8;
    }

    function drawTableHeader(state) {
        var widths = tableColumnWidths();
        var x = pageMargin;
        var height = 7;
        state.pdf.setFillColor.apply(state.pdf, palette.soft);
        state.pdf.setDrawColor.apply(state.pdf, palette.line);
        state.pdf.rect(pageMargin, state.y, contentWidth, height, 'FD');
        state.pdf.setFont('helvetica', 'bold');
        state.pdf.setFontSize(7.5);
        state.pdf.setTextColor.apply(state.pdf, palette.muted);
        ['Code', 'Course title', 'Units'].forEach(function (label, index) {
            state.pdf.text(label, x + 2, state.y + 4.8);
            x += widths[index];
        });
        state.y += height;
        state.tableHeaderVisible = true;
    }

    function measureCourseRow(state, row, comfortableSpacing) {
        var cells = row.querySelectorAll('td');
        if (cells.length < 3) {
            return null;
        }
        var widths = tableColumnWidths();
        var course = courseDetails(row);
        var titleFontSize = comfortableSpacing ? 9.5 : 9;
        state.pdf.setFont('helvetica', 'bold');
        state.pdf.setFontSize(titleFontSize);
        var titleLines = state.pdf.splitTextToSize(course.title, widths[1] - 4);
        var rowHeight = comfortableSpacing
            ? Math.max(8.5, titleLines.length * 4.5 + 3)
            : Math.max(7, titleLines.length * 4.1 + 2);
        var detailWidth = contentWidth - widths[0] - 4;
        var detailHeight = course.details.reduce(function (total, detail) {
            return total + courseDetailHeight(state, detail, detailWidth, comfortableSpacing);
        }, 0);
        var detailTopGap = comfortableSpacing ? 3 : 0;

        return {
            widths: widths,
            code: structuredText(cells[0]),
            course: course,
            units: structuredText(cells[2]),
            titleFontSize: titleFontSize,
            titleLines: titleLines,
            rowHeight: rowHeight,
            detailX: pageMargin + widths[0] + 2,
            detailWidth: detailWidth,
            detailTopGap: detailTopGap,
            minimumBlockHeight: rowHeight + (course.details.length ? detailTopGap + detailHeight : 0)
                + (comfortableSpacing ? 4 : 3)
        };
    }

    function drawCourseRow(state, row, comfortableSpacing) {
        var layout = measureCourseRow(state, row, comfortableSpacing);
        if (!layout) {
            return;
        }
        var widths = layout.widths;
        var code = layout.code;
        var course = layout.course;
        var units = layout.units;
        var pdf = state.pdf;
        var rowHeight = layout.rowHeight;

        if (!state.tableHeaderVisible || state.y + layout.minimumBlockHeight > pageHeight - pageMargin) {
            if (state.y + layout.minimumBlockHeight > pageHeight - pageMargin) {
                newPage(state);
            }
            drawTableHeader(state);
        }
        if (state.y + layout.minimumBlockHeight > pageHeight - pageMargin) {
            newPage(state);
            drawTableHeader(state);
        }

        var rowTop = state.y;
        pdf.setDrawColor.apply(pdf, palette.line);
        pdf.rect(pageMargin, rowTop, contentWidth, rowHeight);
        pdf.line(pageMargin + widths[0], rowTop, pageMargin + widths[0], rowTop + rowHeight);
        pdf.line(pageMargin + widths[0] + widths[1], rowTop, pageMargin + widths[0] + widths[1], rowTop + rowHeight);
        pdf.setFont('helvetica', 'normal');
        var codeSize = 8;
        pdf.setFontSize(codeSize);
        while (pdf.getTextWidth(code) > widths[0] - 4 && codeSize > 6) {
            codeSize -= 0.25;
            pdf.setFontSize(codeSize);
        }
        pdf.setTextColor.apply(pdf, palette.ink);
        pdf.text(code, pageMargin + 2, rowTop + 4.7);
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(layout.titleFontSize);
        pdf.text(layout.titleLines, pageMargin + widths[0] + 2, rowTop + (comfortableSpacing ? 5.5 : 4.5));
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(8);
        pdf.text(units, pageMargin + widths[0] + widths[1] + widths[2] - 2, rowTop + 4.7, { align: 'right' });
        state.y += rowHeight;
        if (comfortableSpacing && course.details.length) {
            state.y += layout.detailTopGap;
        }

        course.details.forEach(function (detail) {
            drawCourseDetail(state, detail, layout.detailX, layout.detailWidth, comfortableSpacing);
        });
        state.y += comfortableSpacing ? (course.details.length ? 2 : 5) : (course.details.length ? 2.5 : 5);
        addRule(state);
        state.y += comfortableSpacing ? 3 : 2;
    }

    function addCourseTable(state, table, options) {
        var rows = table.querySelectorAll('tbody tr');
        if (!rows.length) {
            return;
        }
        var comfortableSpacing = Boolean(options && options.comfortableSpacing);
        state.tableHeaderVisible = false;
        Array.prototype.forEach.call(rows, function (row) {
            drawCourseRow(state, row, comfortableSpacing);
        });
        state.tableHeaderVisible = false;
        state.y += 3;
    }

    function addBullet(state, text, options) {
        var settings = options || {};
        var indent = settings.indent || 2;
        ensureSpace(state, 5);
        state.pdf.setFillColor.apply(state.pdf, settings.color || palette.ink);
        state.pdf.circle(pageMargin + indent + 0.8, state.y - 1.1, 0.55, 'F');
        return addText(state, text, {
            x: pageMargin + indent + 3,
            width: contentWidth - indent - 3,
            fontSize: settings.fontSize || 8.5,
            color: settings.textColor || palette.ink,
            after: settings.after === undefined ? 1.5 : settings.after
        });
    }

    function addPageFooters(state, label) {
        var pdf = state.pdf;
        var totalPages = pdf.getNumberOfPages();
        for (var page = 1; page <= totalPages; page += 1) {
            pdf.setPage(page);
            pdf.setDrawColor.apply(pdf, palette.line);
            pdf.line(pageMargin, pageHeight - 12, pageWidth - pageMargin, pageHeight - 12);
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(8);
            pdf.setTextColor.apply(pdf, palette.muted);
            pdf.text(pdfText(label), pageMargin, pageHeight - 7);
            pdf.text('Page ' + page + ' of ' + totalPages, pageWidth - pageMargin, pageHeight - 7, { align: 'right' });
        }
    }

    function exportGeneratedDraft(run) {
        var state = createPdf();
        var heading = run.querySelector('.run-header h2');
        var metadata = run.querySelector('.run-meta');
        var title = heading ? structuredText(heading) : 'Generated Curriculum Draft';
        var runNumber = structuredText(run.querySelector('.draft-run-number'));
        addHeader(state, 'Generated Curriculum Draft', title, metadata, runNumber, metaValue(metadata, 'Prompt'));

        run.querySelectorAll('.curriculum > section').forEach(function (yearSection) {
            var yearHeading = yearSection.querySelector('.year-heading');
            if (yearHeading && yearSection.querySelector('.term table tbody tr')) {
                addSectionHeading(state, structuredText(yearHeading), 35);
            }
            yearSection.querySelectorAll('.term').forEach(function (term) {
                var termHeading = term.querySelector('.term-heading strong');
                var table = term.querySelector('table');
                if (table && table.querySelector('tbody tr')) {
                    var firstCourseRow = table.querySelector('tbody tr');
                    var firstCourseLayout = measureCourseRow(state, firstCourseRow, true);
                    var termHeadingHeight = termHeading ? 10 * 0.44 + 3 : 0;
                    ensureSpace(state, termHeadingHeight + 7 + (firstCourseLayout ? firstCourseLayout.minimumBlockHeight : 0));
                    if (termHeading) {
                        addText(state, structuredText(termHeading), { fontSize: 10, bold: true, color: palette.ink, after: 3 });
                    }
                    addCourseTable(state, table, { comfortableSpacing: true });
                }
            });
        });

        var filename = 'curriculum-draft-' + safeFilename(title) + '-' + safeFilename(runNumber || 'run') + '.pdf';
        addPageFooters(state, "Curri'KoToh | " + (runNumber || 'run'));
        state.pdf.save(filename);
    }

    function reviewParagraphs(item) {
        return Array.prototype.filter.call(item.querySelectorAll(':scope > p'), function (paragraph) {
            var text = structuredText(paragraph);
            return text && !/^sources?:?\s*$/i.test(text);
        });
    }

    function reviewSources(item) {
        var sources = [];
        Array.prototype.forEach.call(item.querySelectorAll('ul'), function (list) {
            if (list.closest('.job-postings')) {
                return;
            }
            Array.prototype.forEach.call(list.querySelectorAll(':scope > li'), function (source) {
                if (!source.classList.contains('job-postings')) {
                    var sourceText = elementTextWithLinks(source);
                    if (sourceText) {
                        sources.push(sourceText);
                    }
                }
            });
        });
        return sources;
    }

    function estimateTextHeight(state, text, fontSize, width) {
        state.pdf.setFont('helvetica', 'normal');
        state.pdf.setFontSize(fontSize);
        return Math.max(fontSize * 0.44, state.pdf.splitTextToSize(pdfText(text), width).length * fontSize * 0.44) + 2;
    }

    function estimateReviewItemHeight(state, item) {
        var title = item.querySelector(':scope > strong') || item.querySelector('strong');
        var status = item.querySelector('.status-badge');
        var titleText = title ? structuredText(title) + (status ? '  ' + structuredText(status) : '') : '';
        var directText = Array.prototype.filter.call(item.childNodes, function (node) {
            return node.nodeType === Node.TEXT_NODE;
        }).map(function (node) {
            return node.nodeValue || '';
        }).join(' ').trim();
        var paragraphs = reviewParagraphs(item);
        var height = titleText ? estimateTextHeight(state, titleText, 10, contentWidth) : 0;
        if (directText) {
            height += estimateTextHeight(state, directText, 8.5, contentWidth - 5);
        }
        paragraphs.forEach(function (paragraph) {
            height += estimateTextHeight(state, elementTextWithLinks(paragraph), 8.5, contentWidth - 2);
        });
        height += reviewSources(item).length * 5;
        var postings = postingRecords(item.querySelector('.job-postings'));
        return height + (postings.postings.length + postings.listings.length) * 10 + 5;
    }

    function addSources(state, item) {
        var sources = reviewSources(item);
        if (!sources.length) {
            return;
        }
        addText(state, 'Sources', { fontSize: 8, bold: true, color: palette.muted, after: 1 });
        sources.forEach(function (source) {
            addBullet(state, source, { fontSize: 7.8, textColor: palette.muted, after: 0.8 });
        });
    }

    function postingRecords(jobBlock) {
        var postings = [];
        var listings = [];
        if (!jobBlock) {
            return { postings: postings, listings: listings };
        }
        Array.prototype.forEach.call(jobBlock.querySelectorAll('ul li'), function (item) {
            var link = item.querySelector('a[href]');
            if (!link) {
                return;
            }
            var originalLabel = pdfText(link.textContent || '').trim();
            var isListing = /^job listings? page:/i.test(originalLabel);
            var label = originalLabel
                .replace(/^job posting:\s*/i, '')
                .replace(/^job listings? page:\s*/i, '');
            var urlNode = item.querySelector('.posting-url');
            var url = pdfText(urlNode ? urlNode.textContent : (link.href || link.getAttribute('href') || ''));
            var domain = '';
            try {
                domain = new URL(url).hostname;
            } catch (error) {
                domain = '';
            }
            (isListing ? listings : postings).push({
                label: label || domain || url,
                domain: domain,
                url: url
            });
        });
        return { postings: postings, listings: listings };
    }

    function addJobPostingGroups(state, item) {
        var jobBlock = item.querySelector('.job-postings');
        var groups = postingRecords(jobBlock);
        if (!jobBlock) {
            return;
        }
        var note = jobBlock.querySelector('strong');
        var noteText = note ? structuredText(note) : '';
        if (noteText && (groups.postings.length || groups.listings.length)) {
            addText(state, noteText, { fontSize: 7.5, color: palette.muted, after: 1.5 });
        }
        [
            ['Job postings', groups.postings],
            ['Job listing pages', groups.listings]
        ].forEach(function (group) {
            if (!group[1].length) {
                return;
            }
            ensureSpace(state, 12);
            addText(state, group[0], { fontSize: 8, bold: true, color: palette.muted, after: 1 });
            group[1].forEach(function (record) {
                addBullet(state, record.label + (record.domain ? ' · ' + record.domain : ''), {
                    fontSize: 7.8, textColor: palette.ink, after: 0.5
                });
                addText(state, record.url, {
                    x: pageMargin + 7, width: contentWidth - 7, fontSize: 7.2, color: palette.muted, after: 1.2
                });
            });
        });
        Array.prototype.forEach.call(jobBlock.querySelectorAll(':scope > p'), function (paragraph) {
            var text = structuredText(paragraph);
            if (text) {
                addText(state, text, { fontSize: 7.3, color: palette.muted, after: 1.3 });
            }
        });
    }

    function addReviewItem(state, item) {
        var title = item.querySelector(':scope > strong') || item.querySelector('strong');
        var status = item.querySelector('.status-badge');
        var titleText = title ? structuredText(title) : '';
        var statusText = status ? structuredText(status) : '';
        var paragraphs = reviewParagraphs(item);
        var sources = reviewSources(item);
        var postings = postingRecords(item.querySelector('.job-postings'));
        var blockEstimate = 12 + paragraphs.length * 5 + sources.length * 4
            + (postings.postings.length + postings.listings.length) * 9;
        ensureSpace(state, Math.min(blockEstimate, pageHeight - pageMargin * 2));
        if (titleText) {
            addText(state, titleText + (statusText ? '  ·  ' + statusText.toUpperCase() : ''), {
                fontSize: 10, bold: true, color: palette.navy, after: 2.5
            });
        }
        paragraphs.forEach(function (paragraph) {
            addText(state, elementTextWithLinks(paragraph), {
                x: pageMargin + 2, width: contentWidth - 2, fontSize: 8.5, color: palette.ink, after: 2
            });
        });
        addSources(state, item);
        addJobPostingGroups(state, item);
        state.y += 3;
    }

    function exportEnhancementReview(review) {
        var state = createPdf();
        var heading = review.querySelector('.output-header h2');
        var metadata = review.querySelector('.output-meta');
        var title = heading ? structuredText(heading) : 'Enhancement Review';
        var runNumber = structuredText(review.querySelector('.draft-run-number'));
        var creatorSummary = review.querySelector('.draft-created-by');
        var creator = creatorSummary ? structuredText(creatorSummary).replace(/^Created by:\s*/i, '') : '';
        addHeader(
            state,
            'Curriculum Enhancement Results',
            title,
            metadata,
            runNumber,
            metaValue(metadata, 'Prompt'),
            { createdBy: creator }
        );

        review.querySelectorAll('.review-section:not(.pdf-export-hide)').forEach(function (section) {
            if (section.classList.contains('enhanced-curriculum-output')) {
                var years = section.querySelectorAll('.enhanced-year');
                if (!years.length || !section.querySelector('.course-table tbody tr')) {
                    return;
                }
                addSectionHeading(state, structuredText(section.querySelector(':scope > h3')), 45);
                Array.prototype.forEach.call(years, function (year) {
                    var yearHeading = year.querySelector('h4');
                    var firstYearCourseRow = year.querySelector('.enhanced-term table tbody tr');
                    if (yearHeading && firstYearCourseRow) {
                        var firstYearCourseLayout = measureCourseRow(state, firstYearCourseRow, true);
                        var firstTermHeading = year.querySelector('.enhanced-term h5');
                        var firstTermHeadingHeight = firstTermHeading ? 10 * 0.44 + 3 : 0;
                        var yearStartHeight = 17 + firstTermHeadingHeight + 7
                            + (firstYearCourseLayout ? firstYearCourseLayout.minimumBlockHeight : 0);
                        ensureSpace(state, yearStartHeight);
                        addSectionHeading(state, structuredText(yearHeading), 35);
                    }
                    Array.prototype.forEach.call(year.querySelectorAll('.enhanced-term'), function (term) {
                        var termHeading = term.querySelector('h5');
                        var table = term.querySelector('table');
                        var firstCourseRow = table && table.querySelector('tbody tr');
                        if (!firstCourseRow) {
                            return;
                        }
                        var firstCourseLayout = measureCourseRow(state, firstCourseRow, true);
                        var termHeadingHeight = termHeading ? 10 * 0.44 + 3 : 0;
                        ensureSpace(state, termHeadingHeight + 7 + (firstCourseLayout ? firstCourseLayout.minimumBlockHeight : 0));
                        if (termHeading) {
                            addText(state, structuredText(termHeading), { fontSize: 10, bold: true, color: palette.ink, after: 3 });
                        }
                        addCourseTable(state, table, { comfortableSpacing: true });
                    });
                });
                return;
            }

            var summary = section.querySelector('.review-summary');
            var reviewItems = section.querySelectorAll('.review-list .review-item');
            var recommendations = section.querySelectorAll('.recommendation-list > li');
            var sectionTitle = section.querySelector(':scope > h3');
            var sectionName = sectionTitle ? structuredText(sectionTitle) : '';
            if (summary && structuredText(summary)) {
                addSectionHeading(
                    state,
                    sectionName,
                    estimateTextHeight(state, elementTextWithLinks(summary), 8.5, contentWidth) + 3
                );
                addText(state, elementTextWithLinks(summary), {
                    x: pageMargin,
                    width: contentWidth,
                    fontSize: 8.5,
                    color: palette.ink,
                    after: 4
                });
            }
            if (reviewItems.length) {
                addSectionHeading(state, sectionName, estimateReviewItemHeight(state, reviewItems[0]));
                Array.prototype.forEach.call(reviewItems, function (item) {
                    addReviewItem(state, item);
                });
            }
            if (recommendations.length) {
                addSectionHeading(state, sectionName, estimateReviewItemHeight(state, recommendations[0]));
                Array.prototype.forEach.call(recommendations, function (item) {
                    var titleNode = item.querySelector(':scope > strong') || item.querySelector('strong');
                    var titleText = titleNode ? structuredText(titleNode) : '';
                    var firstText = '';
                    if (titleText) {
                        firstText = Array.prototype.filter.call(item.childNodes, function (node) {
                            return node.nodeType === Node.TEXT_NODE;
                        }).map(function (node) {
                            return node.nodeValue || '';
                        }).join(' ').trim();
                    }
                    var paragraphs = reviewParagraphs(item);
                    var sources = reviewSources(item);
                    var postingGroups = postingRecords(item.querySelector('.job-postings'));
                    ensureSpace(state, Math.min(16 + paragraphs.length * 5 + sources.length * 4
                        + (postingGroups.postings.length + postingGroups.listings.length) * 9, pageHeight - pageMargin * 2));
                    if (titleText) {
                        addBullet(state, titleText + (firstText ? ' ' + firstText : ''), {
                            fontSize: 8.5, textColor: palette.ink, after: 2
                        });
                    }
                    paragraphs.forEach(function (paragraph) {
                        addText(state, elementTextWithLinks(paragraph), {
                            x: pageMargin + 5, width: contentWidth - 5, fontSize: 8, color: palette.ink, after: 1.5
                        });
                    });
                    addSources(state, item);
                    addJobPostingGroups(state, item);
                    state.y += 2;
                });
            }
        });

        var filename = 'enhancement-results-' + safeFilename(title) + '-' + safeFilename(runNumber) + '.pdf';
        addPageFooters(state, "Curri'KoToh | " + (runNumber || 'run'));
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