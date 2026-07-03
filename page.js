const STORAGE_KEY = 'owwa-scholar-ledger';
const IMPORT_CONFIG = window.OWWA_IMPORT_CONFIG || { columnAliases: {} };

const seedLedger = [
	{
		id: crypto.randomUUID(),
		scholarId: 'OWWA-2026-001',
		fullName: 'Maria Santos',
		program: 'Education',
		batch: 'Batch 2026-A',
		amountDue: 15000,
		amountPaid: 15000,
		dueDate: '2026-05-05',
		paymentDate: '2026-05-02',
		notes: 'Cleared for the current term.'
	},
	{
		id: crypto.randomUUID(),
		scholarId: 'OWWA-2026-002',
		fullName: 'Ramon Reyes',
		program: 'Maritime',
		batch: 'Batch 2026-B',
		amountDue: 18000,
		amountPaid: 7000,
		dueDate: '2026-05-20',
		paymentDate: '',
		notes: 'Reminder sent for remaining balance.'
	},
	{
		id: crypto.randomUUID(),
		scholarId: 'OWWA-2026-003',
		fullName: 'Ella Navarro',
		program: 'TESDA',
		batch: 'Batch 2026-A',
		amountDue: 12000,
		amountPaid: 0,
		dueDate: '2026-04-30',
		paymentDate: '',
		notes: 'Pending first payment.'
	},
	{
		id: crypto.randomUUID(),
		scholarId: 'OWWA-2026-004',
		fullName: 'Jose Miguel Cruz',
		program: 'Education',
		batch: 'Batch 2026-C',
		amountDue: 16000,
		amountPaid: 16000,
		dueDate: '2026-05-12',
		paymentDate: '2026-05-10',
		notes: 'Verified and released.'
	}
];

const state = {
	ledger: [],
	filtered: [],
	editingId: null
};

const elements = {};

function normalizeLabel(value) {
	return String(value || '')
		.trim()
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, ' ')
		.replace(/\s+/g, ' ')
		.trim();
}

function parseDate(value) {
	if (!value && value !== 0) {
		return '';
	}

	if (value instanceof Date && !Number.isNaN(value.getTime())) {
		return value.toISOString().slice(0, 10);
	}

	if (typeof value === 'number' && window.XLSX?.SSF?.parse_date_code) {
		const parsed = window.XLSX.SSF.parse_date_code(value);
		if (parsed) {
			const month = String(parsed.m).padStart(2, '0');
			const day = String(parsed.d).padStart(2, '0');
			return `${parsed.y}-${month}-${day}`;
		}
	}

	const text = String(value).trim();
	if (!text) {
		return '';
	}

	const date = new Date(text);
	if (Number.isNaN(date.getTime())) {
		return '';
	}

	return date.toISOString().slice(0, 10);
}

function parseNumber(value) {
	if (typeof value === 'number') {
		return Number.isFinite(value) ? value : 0;
	}

	const cleaned = String(value || '')
		.replace(/[^0-9.-]/g, '')
		.trim();
	return cleaned ? Number(cleaned) || 0 : 0;
}

function findKey(row, aliases) {
	const entries = Object.entries(row);
	for (const [key, value] of entries) {
		const normalizedKey = normalizeLabel(key);
		if (aliases.some((alias) => normalizedKey === normalizeLabel(alias) || normalizedKey.includes(normalizeLabel(alias)))) {
			return { key, value };
		}
	}

	return { key: '', value: undefined };
}

function pickValue(row, aliases) {
	const { value } = findKey(row, aliases);
	return value;
}

function deriveStatus(record) {
	const explicit = normalizeLabel(record.status);
	if (explicit) {
		if (explicit.includes('paid') || explicit.includes('cleared') || explicit.includes('settled')) {
			return 'paid';
		}
		if (explicit.includes('overdue') || explicit.includes('late')) {
			return 'overdue';
		}
		if (explicit.includes('partial')) {
			return 'partial';
		}
		if (explicit.includes('unpaid') || explicit.includes('pending') || explicit.includes('due')) {
			return 'unpaid';
		}
	}

	return computeStatus(record);
}

function normalizeImportedRow(row, index, fileName) {
	const scholarId = String(pickValue(row, IMPORT_CONFIG.columnAliases.scholarId) ?? `ROW-${index + 1}`).trim() || `ROW-${index + 1}`;
	const fullName = String(pickValue(row, IMPORT_CONFIG.columnAliases.fullName) ?? pickValue(row, ['beneficiary']) ?? `Scholar ${index + 1}`).trim() || `Scholar ${index + 1}`;
	const program = String(pickValue(row, IMPORT_CONFIG.columnAliases.program) ?? 'Unspecified').trim() || 'Unspecified';
	const batch = String(pickValue(row, IMPORT_CONFIG.columnAliases.batch) ?? 'Unspecified').trim() || 'Unspecified';
	const amountDue = parseNumber(pickValue(row, IMPORT_CONFIG.columnAliases.amountDue));
	const amountPaid = parseNumber(pickValue(row, IMPORT_CONFIG.columnAliases.amountPaid));
	const dueDate = parseDate(pickValue(row, IMPORT_CONFIG.columnAliases.dueDate));
	const paymentDate = parseDate(pickValue(row, IMPORT_CONFIG.columnAliases.paymentDate));
	const notes = String(pickValue(row, IMPORT_CONFIG.columnAliases.notes) ?? '').trim();
	const status = deriveStatus({ ...row, amountDue, amountPaid, dueDate });

	return {
		id: crypto.randomUUID(),
		scholarId,
		fullName,
		program,
		batch,
		amountDue,
		amountPaid,
		dueDate,
		paymentDate,
		notes: notes || `Imported from ${fileName}`,
		status
	};
}

function normalizeImportedRecords(rows, fileName) {
	return rows
		.filter((row) => row && typeof row === 'object' && Object.keys(row).length)
		.map((row, index) => normalizeImportedRow(row, index, fileName));
}

function flattenWorkbookToRows(workbook) {
	const sheetName = workbook.SheetNames[0];
	if (!sheetName) {
		return [];
	}

	const sheet = workbook.Sheets[sheetName];
	return window.XLSX.utils.sheet_to_json(sheet, { defval: '' });
}

function workbookToLedger(file, workbook) {
	const rows = flattenWorkbookToRows(workbook);
	if (!rows.length) {
		return [];
	}

	const imported = normalizeImportedRecords(rows, file.name);
	return imported.map((record) => ({
		...record,
		amountDue: Number(record.amountDue || 0),
		amountPaid: Number(record.amountPaid || 0),
		dueDate: record.dueDate || '',
		paymentDate: record.paymentDate || '',
		status: record.status || deriveStatus(record)
	}));
}

function money(value) {
	return new Intl.NumberFormat('en-PH', {
		style: 'currency',
		currency: 'PHP',
		maximumFractionDigits: 2
	}).format(Number(value || 0));
}

function loadLedger() {
	try {
		const stored = localStorage.getItem(STORAGE_KEY);
		if (stored) {
			const parsed = JSON.parse(stored);
			if (Array.isArray(parsed) && parsed.length) {
				return parsed;
			}
		}
	} catch {
		// Fall back to seed data.
	}
	return seedLedger.map((entry) => ({ ...entry }));
}

function saveLedger() {
	localStorage.setItem(STORAGE_KEY, JSON.stringify(state.ledger));
}

function computeStatus(record) {
	const due = Number(record.amountDue || 0);
	const paid = Number(record.amountPaid || 0);
	const balance = Math.max(due - paid, 0);
	const overdue = balance > 0 && new Date(record.dueDate) < startOfToday();

	if (paid >= due && due > 0) {
		return 'paid';
	}

	if (overdue) {
		return 'overdue';
	}

	if (paid > 0) {
		return 'partial';
	}

	return 'unpaid';
}

function startOfToday() {
	const today = new Date();
	today.setHours(0, 0, 0, 0);
	return today;
}

function getFilteredLedger() {
	const search = elements.searchInput.value.trim().toLowerCase();
	const statusFilter = elements.statusFilter.value;
	const programFilter = elements.programFilter.value;
	const balanceFilter = elements.balanceFilter.value;

	return state.ledger.filter((record) => {
		const status = computeStatus(record);
		const balance = Math.max(Number(record.amountDue || 0) - Number(record.amountPaid || 0), 0);
		const haystack = [record.scholarId, record.fullName, record.program, record.batch, record.notes]
			.join(' ')
			.toLowerCase();

		const matchesSearch = !search || haystack.includes(search);
		const matchesStatus = statusFilter === 'all' || status === statusFilter;
		const matchesProgram = programFilter === 'all' || record.program === programFilter;
		const matchesBalance =
			balanceFilter === 'all' ||
			(balanceFilter === 'due' && balance > 0) ||
			(balanceFilter === 'clear' && balance === 0) ||
			(balanceFilter === 'overdue' && status === 'overdue');

		return matchesSearch && matchesStatus && matchesProgram && matchesBalance;
	});
}

function renderStatusOptions() {
	const options = [
		['all', 'All statuses'],
		['paid', 'Paid'],
		['partial', 'Partial payment'],
		['unpaid', 'Unpaid'],
		['overdue', 'Overdue']
	];

	elements.statusFilter.innerHTML = options
		.map(([value, label]) => `<option value="${value}">${label}</option>`)
		.join('');
}

function renderProgramOptions() {
	const currentValue = elements.programFilter.value || 'all';
	const programs = [...new Set(state.ledger.map((entry) => entry.program))].sort();
	elements.programFilter.innerHTML = [`<option value="all">All programs</option>`, ...programs.map((program) => `<option value="${program}">${program}</option>`)].join('');
	if ([...elements.programFilter.options].some((option) => option.value === currentValue)) {
		elements.programFilter.value = currentValue;
	}
}

function renderStats(records) {
	const totalDue = records.reduce((sum, record) => sum + Number(record.amountDue || 0), 0);
	const totalPaid = records.reduce((sum, record) => sum + Number(record.amountPaid || 0), 0);
	const outstanding = Math.max(totalDue - totalPaid, 0);
	const paidCount = records.filter((record) => computeStatus(record) === 'paid').length;
	const overdueCount = records.filter((record) => computeStatus(record) === 'overdue').length;
	const completion = totalDue ? Math.round((totalPaid / totalDue) * 100) : 0;

	elements.stats.innerHTML = [
		{ label: 'Total scholars', value: records.length, detail: 'Records in the current result set' },
		{ label: 'Paid scholars', value: paidCount, detail: `${completion}% completion` },
		{ label: 'Total collected', value: money(totalPaid), detail: 'Received from scholars' },
		{ label: 'Outstanding', value: money(outstanding), detail: `${overdueCount} overdue account${overdueCount === 1 ? '' : 's'}` }
	]
		.map(
			(item) => `
				<div class="stat-card">
					<div class="label">${item.label}</div>
					<div class="value">${item.value}</div>
					<div class="detail">${item.detail}</div>
				</div>`
		)
		.join('');

	const statusBreakdown = {
		paid: records.filter((record) => computeStatus(record) === 'paid').length,
		partial: records.filter((record) => computeStatus(record) === 'partial').length,
		unpaid: records.filter((record) => computeStatus(record) === 'unpaid').length,
		overdue: records.filter((record) => computeStatus(record) === 'overdue').length
	};

	const totalRecords = Math.max(records.length, 1);
	elements.statusBars.innerHTML = Object.entries(statusBreakdown)
		.map(([status, count]) => {
			const label = status.charAt(0).toUpperCase() + status.slice(1);
			const width = `${Math.round((count / totalRecords) * 100)}%`;
			return `
				<div class="bar-row">
					<div>${label}</div>
					<div class="bar-track"><div class="bar-fill" style="width:${width}"></div></div>
					<div>${count}</div>
				</div>`;
		})
		.join('');
}

function renderQueryPanel(records) {
	const activeFilters = {
		search: elements.searchInput.value.trim() || 'Any text',
		status: elements.statusFilter.value,
		program: elements.programFilter.value,
		balance: elements.balanceFilter.value
	};

	elements.queryPreview.textContent = [
		'SELECT * FROM scholars',
		`WHERE text LIKE ${activeFilters.search === 'Any text' ? '"%"' : `'${activeFilters.search}'`}`,
		`AND status = ${activeFilters.status}`,
		`AND program = ${activeFilters.program}`,
		`AND balance = ${activeFilters.balance}`
	].join('\n');

	if (!records.length) {
		elements.querySummary.textContent = 'No scholars match the current query.';
		return;
	}

	elements.querySummary.textContent = records
		.map((record) => {
			const balance = Math.max(Number(record.amountDue || 0) - Number(record.amountPaid || 0), 0);
			return `${record.scholarId} | ${record.fullName} | ${record.program} | ${computeStatus(record)} | balance ${money(balance)}`;
		})
		.join('\n');
}

function renderComputation(records) {
	const totalDue = records.reduce((sum, record) => sum + Number(record.amountDue || 0), 0);
	const totalPaid = records.reduce((sum, record) => sum + Number(record.amountPaid || 0), 0);
	const outstanding = Math.max(totalDue - totalPaid, 0);
	const completionRate = totalDue ? ((totalPaid / totalDue) * 100).toFixed(1) : '0.0';
	const overdueCount = records.filter((record) => computeStatus(record) === 'overdue').length;
	const averageBalance = records.length ? (outstanding / records.length).toFixed(2) : '0.00';

	elements.computationArea.innerHTML = [
		['Total due', `${money(totalDue)} = Σ amountDue`],
		['Total paid', `${money(totalPaid)} = Σ amountPaid`],
		['Outstanding balance', `${money(outstanding)} = total due - total paid`],
		['Completion rate', `${completionRate}% = total paid / total due × 100`],
		['Overdue scholars', `${overdueCount} records currently past due date`],
		['Average balance per record', `${money(averageBalance)} = outstanding / scholar count`]
	]
		.map(
			([name, value]) => `
				<div class="formula">
					<div class="name">${name}</div>
					<div class="value">${value}</div>
				</div>`
		)
		.join('');
}

function renderTable(records) {
	if (!records.length) {
		elements.ledgerBody.innerHTML = '<tr><td class="empty-state" colspan="7">No records match your current filters.</td></tr>';
		return;
	}

	elements.ledgerBody.innerHTML = records
		.map((record) => {
			const balance = Math.max(Number(record.amountDue || 0) - Number(record.amountPaid || 0), 0);
			const status = computeStatus(record);
			return `
				<tr>
					<td>
						<strong>${record.fullName}</strong><br>
						<span>${record.scholarId}</span><br>
						<small>${record.notes || ''}</small>
					</td>
					<td>${record.program}</td>
					<td>${record.batch}</td>
					<td>${money(record.amountDue)}</td>
					<td>${money(record.amountPaid)}</td>
					<td>${money(balance)}</td>
					<td><span class="pill ${status}">${status}</span></td>
				</tr>`;
		})
		.join('');
}

function syncForm(record) {
	elements.scholarId.value = record?.scholarId || '';
	elements.fullName.value = record?.fullName || '';
	elements.program.value = record?.program || '';
	elements.batch.value = record?.batch || '';
	elements.amountDue.value = record?.amountDue ?? '';
	elements.amountPaid.value = record?.amountPaid ?? '';
	elements.dueDate.value = record?.dueDate || '';
	elements.paymentDate.value = record?.paymentDate || '';
	elements.notes.value = record?.notes || '';
}

function clearForm() {
	state.editingId = null;
	syncForm();
}

function collectFormData() {
	return {
		scholarId: elements.scholarId.value.trim(),
		fullName: elements.fullName.value.trim(),
		program: elements.program.value.trim(),
		batch: elements.batch.value.trim(),
		amountDue: Number(elements.amountDue.value || 0),
		amountPaid: Number(elements.amountPaid.value || 0),
		dueDate: elements.dueDate.value,
		paymentDate: elements.paymentDate.value,
		notes: elements.notes.value.trim()
	};
}

function persistRecord() {
	const payload = collectFormData();

	if (!payload.scholarId || !payload.fullName || !payload.program || !payload.batch || !payload.dueDate) {
		window.alert('Please complete the required scholar fields before saving.');
		return;
	}

	if (state.editingId) {
		state.ledger = state.ledger.map((record) => (record.id === state.editingId ? { ...record, ...payload } : record));
	} else {
		state.ledger.unshift({
			id: crypto.randomUUID(),
			...payload
		});
	}

	saveLedger();
	refresh();
	clearForm();
}

function deleteRecord(id) {
	const record = state.ledger.find((entry) => entry.id === id);
	if (!record) {
		return;
	}

	const confirmed = window.confirm(`Delete ${record.fullName}?`);
	if (!confirmed) {
		return;
	}

	state.ledger = state.ledger.filter((entry) => entry.id !== id);
	saveLedger();
	refresh();
}

function editRecord(id) {
	const record = state.ledger.find((entry) => entry.id === id);
	if (!record) {
		return;
	}

	state.editingId = id;
	syncForm(record);
}

function refresh() {
	renderProgramOptions();
	const filtered = getFilteredLedger();
	state.filtered = filtered;
	renderStats(filtered);
	renderTable(filtered);
}

function loadDemoData() {
	state.ledger = seedLedger.map((entry) => ({ ...entry, id: crypto.randomUUID() }));
	saveLedger();
	clearForm();
	refresh();
}

async function importExcelFile() {
	const file = elements.excelFile.files?.[0];
	if (!file) {
		window.alert('Choose an Excel file first.');
		return;
	}

	if (!window.XLSX) {
		window.alert('Excel parser is unavailable. Make sure you are connected to the internet so the SheetJS library can load.');
		return;
	}

	try {
		const buffer = await file.arrayBuffer();
		const workbook = window.XLSX.read(buffer, { type: 'array' });
		const importedLedger = workbookToLedger(file, workbook);

		if (!importedLedger.length) {
			window.alert('The workbook did not contain any usable rows.');
			return;
		}

		state.ledger = importedLedger;
		saveLedger();
		clearForm();
		refresh();
		window.alert(`Imported ${importedLedger.length} scholar record${importedLedger.length === 1 ? '' : 's'} from ${file.name}.`);
	} catch (error) {
		console.error(error);
		window.alert('Could not read the Excel file. Please confirm it is a valid .xlsx, .xls, or .csv file.');
	}
}

function downloadTemplate() {
	const headers = [
		'scholarId',
		'fullName',
		'program',
		'batch',
		'amountDue',
		'amountPaid',
		'dueDate',
		'paymentDate',
		'notes',
		'status'
	];
	const sample = [
		['OWWA-2026-001', 'Maria Santos', 'Education', 'Batch 2026-A', 15000, 15000, '2026-05-05', '2026-05-02', 'Cleared', 'paid']
	];
	const worksheet = window.XLSX.utils.aoa_to_sheet([headers, ...sample]);
	const workbook = window.XLSX.utils.book_new();
	window.XLSX.utils.book_append_sheet(workbook, worksheet, 'ScholarLedger');
	window.XLSX.writeFile(workbook, 'owwa-scholar-ledger-template.xlsx');
}

function resetQuery() {
	elements.searchInput.value = '';
	elements.statusFilter.value = 'all';
	elements.programFilter.value = 'all';
	elements.balanceFilter.value = 'all';
	refresh();
}

function bindEvents() {
	[elements.searchInput, elements.statusFilter, elements.programFilter, elements.balanceFilter].forEach((control) => {
		control.addEventListener('input', refresh);
		control.addEventListener('change', refresh);
	});
}

function initialize() {
	elements.stats = document.getElementById('stats');
	elements.statusBars = document.getElementById('statusBars');
	elements.ledgerBody = document.getElementById('ledgerBody');
	elements.searchInput = document.getElementById('searchInput');
	elements.statusFilter = document.getElementById('statusFilter');
	elements.programFilter = document.getElementById('programFilter');
	elements.balanceFilter = document.getElementById('balanceFilter');

	state.ledger = loadLedger();
	renderStatusOptions();
	bindEvents();
	resetQuery();
}

document.addEventListener('DOMContentLoaded', initialize);
