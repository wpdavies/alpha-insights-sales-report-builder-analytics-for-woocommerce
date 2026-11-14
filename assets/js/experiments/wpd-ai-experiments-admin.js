/**
 * Experiments admin: code editors and repeaters.
 */
(function ($) {
	'use strict';

	function canInitEditor(settings) {
		return !!(window.wp && wp.codeEditor && settings && settings !== false);
	}

	function editorSettingsFor($textarea) {
		if (!window.wpdAiExperimentsAdmin) {
			return false;
		}
		var cfg = window.wpdAiExperimentsAdmin;
		if ($textarea.hasClass('wpd-exp-css')) {
			return cfg.cssSettings;
		}
		if ($textarea.hasClass('wpd-exp-js')) {
			return cfg.jsSettings;
		}
		if ($textarea.hasClass('wpd-exp-php')) {
			return cfg.phpSettings;
		}
		return false;
	}

	function initEditors(scope) {
		$(scope)
			.find('textarea.wpd-exp-code')
			.each(function () {
				var $textarea = $(this);
				if ($textarea.closest('.wpd-exp-variant-code.hidden, .wpd-exp-code-panel.hidden').length) {
					return;
				}
				if ($textarea.data('cm') || $textarea.prop('disabled')) {
					return;
				}
				var settings = editorSettingsFor($textarea);
				if (!canInitEditor(settings)) {
					return;
				}
				wp.codeEditor.initialize(this, settings);
				$textarea.data('cm', 1);
			});
	}

	function refreshVisibleEditors($scope) {
		$scope.find('.CodeMirror').each(function () {
			if (this.CodeMirror && typeof this.CodeMirror.refresh === 'function') {
				this.CodeMirror.refresh();
			}
		});
	}

	function syncCodeTabIndicators($variant) {
		flushCodeMirrors();
		$variant.find('.wpd-exp-code-tab').each(function () {
			var tab = $(this).attr('data-code-tab');
			var val = $variant.find('.wpd-exp-code-panel[data-code-panel="' + tab + '"] textarea.wpd-exp-code').val();
			$(this).toggleClass('has-code', $.trim(val || '') !== '');
		});
	}

	function showVariantCodeTab($variant, tab) {
		syncCodeTabIndicators($variant);
		$variant.find('.wpd-exp-code-tab').removeClass('is-active').attr('aria-selected', 'false');
		$variant.find('.wpd-exp-code-tab[data-code-tab="' + tab + '"]').addClass('is-active').attr('aria-selected', 'true');
		$variant.find('.wpd-exp-code-panel').addClass('hidden');
		var $panel = $variant.find('.wpd-exp-code-panel[data-code-panel="' + tab + '"]').removeClass('hidden');
		initEditors($panel);
		window.setTimeout(function () {
			refreshVisibleEditors($panel);
		}, 0);
	}

	function flushCodeMirrors() {
		$('.CodeMirror').each(function () {
			if (this.CodeMirror && typeof this.CodeMirror.save === 'function') {
				this.CodeMirror.save();
			}
		});
	}

	function toSlug(name) {
		if (window.wp && wp.url && typeof wp.url.cleanForSlug === 'function') {
			return wp.url.cleanForSlug(name || '');
		}
		return String(name || '')
			.toLowerCase()
			.replace(/['’]/g, '')
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');
	}

	function slugIsAutogen() {
		return $('#wpd-exp-slug-box').attr('data-autogen') === '1';
	}

	function setSlugAutogen(enabled) {
		$('#wpd-exp-slug-box').attr('data-autogen', enabled ? '1' : '0');
	}

	function setSlugValue(slug) {
		var value = slug || '';
		$('#wpd-exp-slug').val(value);
		$('#wpd-exp-slug-display').text(value || '—');
	}

	function syncSlugFromName() {
		if (!slugIsAutogen()) {
			return;
		}
		setSlugValue(toSlug($('#wpd-exp-name').val()));
	}

	function showSlugEditor(show) {
		$('#wpd-exp-slug-view').toggle(!show);
		$('#wpd-exp-slug-editor').prop('hidden', !show);
		if (show) {
			$('#wpd-exp-slug').trigger('focus').trigger('select');
		}
	}

	function initSlugPermalink() {
		var $name = $('#wpd-exp-name');
		var $slug = $('#wpd-exp-slug');
		if (!$name.length || !$slug.length) {
			return;
		}

		var slugBeforeEdit = $slug.val();

		$name.on('input', syncSlugFromName);

		$('#wpd-exp-slug-edit').on('click', function (e) {
			e.preventDefault();
			slugBeforeEdit = $slug.val();
			if (!$slug.val()) {
				setSlugValue(toSlug($name.val()));
			}
			showSlugEditor(true);
		});

		$('#wpd-exp-slug-ok').on('click', function (e) {
			e.preventDefault();
			var custom = toSlug($slug.val());
			if (!custom) {
				custom = toSlug($name.val());
				setSlugAutogen(true);
			} else if (custom !== toSlug($name.val())) {
				setSlugAutogen(false);
			}
			setSlugValue(custom);
			showSlugEditor(false);
		});

		$('#wpd-exp-slug-cancel').on('click', function (e) {
			e.preventDefault();
			setSlugValue(slugBeforeEdit);
			showSlugEditor(false);
		});

		$slug.on('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				$('#wpd-exp-slug-ok').trigger('click');
			}
			if (e.key === 'Escape') {
				e.preventDefault();
				$('#wpd-exp-slug-cancel').trigger('click');
			}
		});

		syncSlugFromName();
	}

	function getObjectives() {
		return (window.wpdAiExperimentsAdmin && wpdAiExperimentsAdmin.objectives) || {};
	}

	function isProBuild() {
		return !!(window.wpdAiExperimentsAdmin && Number(wpdAiExperimentsAdmin.isPro));
	}

	function isProObjective(goal) {
		var objectives = getObjectives();
		if (objectives[goal] && typeof objectives[goal].pro !== 'undefined') {
			return !!objectives[goal].pro;
		}
		return goal && goal !== 'transaction';
	}

	function freeFallbackGoal() {
		return 'transaction';
	}

	function triggerExperimentUpgrade() {
		var $link = $('.wpd-trigger-upgrade-modal').first();
		if ($link.length) {
			$link.trigger('click');
		}
	}

	function guardProGoal($select, goal, silent) {
		if (isProBuild() || !isProObjective(goal)) {
			return goal;
		}
		$select.val(freeFallbackGoal());
		if (!silent) {
			triggerExperimentUpgrade();
		}
		return freeFallbackGoal();
	}

	function formatNumber(value, decimals) {
		var n = Number(value);
		if (!isFinite(n)) {
			n = 0;
		}
		decimals = typeof decimals === 'number' ? decimals : 0;
		return n.toLocaleString(undefined, {
			minimumFractionDigits: decimals,
			maximumFractionDigits: decimals
		});
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function formatMoney(value) {
		var cfg = (window.wpdAiExperimentsAdmin && wpdAiExperimentsAdmin.currency) || {};
		var n = Number(value);
		if (!isFinite(n)) {
			n = 0;
		}
		var decimals = parseInt(cfg.decimals, 10);
		if (isNaN(decimals)) {
			decimals = 2;
		}
		var abs = Math.abs(n).toFixed(decimals).split('.');
		var thousand = cfg.thousand || ',';
		var decimal = cfg.decimal || '.';
		abs[0] = abs[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousand);
		var number = abs.join(decimal);
		if (n < 0) {
			number = '-' + number;
		}
		var symbol = cfg.symbol || '$';
		switch (cfg.position) {
			case 'left_space':
				return symbol + ' ' + number;
			case 'right':
				return number + symbol;
			case 'right_space':
				return number + ' ' + symbol;
			default:
				return symbol + number;
		}
	}

	function readArm($tr) {
		return {
			exposures: parseFloat($tr.attr('data-exposures')) || 0,
			transactions: parseFloat($tr.attr('data-transactions')) || 0,
			addToCarts: parseFloat($tr.attr('data-add-to-carts')) || 0,
			initiateCheckouts: parseFloat($tr.attr('data-initiate-checkouts')) || 0,
			revenue: parseFloat($tr.attr('data-revenue')) || 0,
			aov: parseFloat($tr.attr('data-aov')) || 0,
			isControl: $tr.attr('data-is-control') === '1'
		};
	}

	function armMetric(arm, goal) {
		var exposures = arm.exposures;
		if (goal === 'add_to_cart') {
			return { value: arm.addToCarts, rate: exposures ? arm.addToCarts / exposures : 0, kind: 'count' };
		}
		if (goal === 'initiate_checkout') {
			return { value: arm.initiateCheckouts, rate: exposures ? arm.initiateCheckouts / exposures : 0, kind: 'count' };
		}
		if (goal === 'purchase_value') {
			return { value: arm.revenue, rate: exposures ? arm.revenue / exposures : 0, kind: 'currency' };
		}
		if (goal === 'revenue_per_exposure') {
			var rpe = exposures ? arm.revenue / exposures : 0;
			return { value: rpe, rate: rpe, kind: 'currency' };
		}
		if (goal === 'aov') {
			return { value: arm.aov, rate: arm.aov, kind: 'currency' };
		}
		return { value: arm.transactions, rate: exposures ? arm.transactions / exposures : 0, kind: 'count' };
	}

	function getProgressColors() {
		return ['#138fdd', '#28a745', '#e67e22', '#8e44ad', '#16a085', '#c0392b'];
	}

	function parseProgress($root) {
		var raw = $root.attr('data-progress');
		if (!raw) {
			return null;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	function cumulativeSeries(arm, goal) {
		var exposures = arm.exposures || [];
		var addToCarts = arm.add_to_carts || [];
		var checkouts = arm.initiate_checkouts || [];
		var transactions = arm.transactions || [];
		var revenue = arm.revenue || [];
		var exp = 0;
		var atc = 0;
		var ic = 0;
		var tx = 0;
		var rev = 0;
		var points = [];
		var i;
		for (i = 0; i < exposures.length; i++) {
			exp += Number(exposures[i]) || 0;
			atc += Number(addToCarts[i]) || 0;
			ic += Number(checkouts[i]) || 0;
			tx += Number(transactions[i]) || 0;
			rev += Number(revenue[i]) || 0;
			var rate = 0;
			if (goal === 'add_to_cart') {
				rate = exp ? atc / exp : 0;
			} else if (goal === 'initiate_checkout') {
				rate = exp ? ic / exp : 0;
			} else if (goal === 'purchase_value' || goal === 'revenue_per_exposure') {
				rate = exp ? rev / exp : 0;
			} else if (goal === 'aov') {
				rate = tx ? rev / tx : 0;
			} else {
				rate = exp ? tx / exp : 0;
			}
			points.push(rate);
		}
		return points;
	}

	function isCurrencyGoal(goal) {
		return goal === 'aov' || goal === 'purchase_value' || goal === 'revenue_per_exposure';
	}

	function armSuccesses(arm, goal) {
		if (goal === 'add_to_cart') {
			return arm.addToCarts || 0;
		}
		if (goal === 'initiate_checkout') {
			return arm.initiateCheckouts || 0;
		}
		return arm.transactions || 0;
	}

	function experimentErf(x) {
		var sign = x < 0 ? -1 : 1;
		x = Math.abs(x);
		var t = 1 / (1 + 0.3275911 * x);
		var y = 1 - (((((1.061405429 * t - 1.453152027) * t) + 1.421413741) * t - 0.284496736) * t + 0.254829592) * t * Math.exp(-x * x);
		return sign * y;
	}

	function experimentNormalCdf(x) {
		return 0.5 * (1 + experimentErf(x / Math.sqrt(2)));
	}

	function twoProportionZ(c1, n1, c2, n2) {
		if (n1 < 1 || n2 < 1) {
			return null;
		}
		var p = (c1 + c2) / (n1 + n2);
		var se = Math.sqrt(p * (1 - p) * ((1 / n1) + (1 / n2)));
		if (se <= 0) {
			return null;
		}
		return ((c2 / n2) - (c1 / n1)) / se;
	}

	function wilsonInterval(successes, n) {
		if (n < 1) {
			return { low: 0, high: 0 };
		}
		var z = 1.96;
		var p = successes / n;
		var z2 = z * z;
		var denom = 1 + (z2 / n);
		var center = (p + (z2 / (2 * n))) / denom;
		var margin = (z * Math.sqrt(((p * (1 - p)) / n) + (z2 / (4 * n * n)))) / denom;
		return {
			low: Math.max(0, center - margin),
			high: Math.min(1, center + margin)
		};
	}

	function significanceResult(c1, n1, c2, n2) {
		var i18n = (window.wpdAiExperimentsAdmin && wpdAiExperimentsAdmin.i18n) || {};
		var p1 = n1 > 0 ? c1 / n1 : 0;
		var p2 = n2 > 0 ? c2 / n2 : 0;
		var lift = n1 > 0 && p1 > 0 ? ((p2 - p1) / p1) * 100 : null;
		var ci = wilsonInterval(c2, n2);
		var pooled = (n1 + n2) > 0 ? (c1 + c2) / (n1 + n2) : 0;
		var ready = n1 >= 100 && n2 >= 100 && (n1 * pooled) >= 5 && (n2 * pooled) >= 5 && (n1 * (1 - pooled)) >= 5 && (n2 * (1 - pooled)) >= 5;
		var result = {
			status: 'needs_data',
			label: i18n.needsMoreData || 'Needs more data',
			summary: i18n.sigNeedsData || 'Need about 100 visitors in each arm, with enough conversions, before this result is reliable.',
			pValue: null,
			lift: lift,
			rateLow: ci.low,
			rateHigh: ci.high,
			direction: lift > 0 ? 'up' : (lift < 0 ? 'down' : 'flat'),
			ready: ready
		};

		if (n1 < 1 || n2 < 1) {
			return result;
		}

		var z = twoProportionZ(c1, n1, c2, n2);
		if (z === null) {
			return result;
		}

		var pValue = 2 * (1 - experimentNormalCdf(Math.abs(z)));
		pValue = Math.round(Math.min(1, Math.max(0, pValue)) * 10000) / 10000;
		result.pValue = pValue;

		if (!ready) {
			return result;
		}

		if (pValue <= 0.05) {
			result.status = 'significant';
			result.label = i18n.significant || 'Significant';
			result.summary = result.direction === 'up' ? (i18n.sigAhead || '') : (i18n.sigBehind || '');
			return result;
		}

		if (pValue <= 0.20) {
			result.status = 'directional';
			result.label = i18n.directional || 'Directional';
			result.summary = i18n.sigDirectional || '';
			return result;
		}

		result.status = 'inconclusive';
		result.label = i18n.inconclusive || 'Inconclusive';
		result.summary = i18n.sigInconclusive || '';
		return result;
	}

	function formatPValue(value) {
		var template = (window.wpdAiExperimentsAdmin && wpdAiExperimentsAdmin.i18n && wpdAiExperimentsAdmin.i18n.pValue) || 'p = %s';
		return template.replace('%s', formatNumber(value, 3));
	}

	function formatCi(low, high) {
		var template = (window.wpdAiExperimentsAdmin && wpdAiExperimentsAdmin.i18n && wpdAiExperimentsAdmin.i18n.ciLabel) || '95%% CI %s–%s';
		return template
			.replace('%%', '%')
			.replace('%s', formatNumber(low * 100, 1) + '%')
			.replace('%s', formatNumber(high * 100, 1) + '%');
	}

	function renderSignificanceHtml(arm, result, goal) {
		var i18n = (window.wpdAiExperimentsAdmin && wpdAiExperimentsAdmin.i18n) || {};
		var ci = wilsonInterval(armSuccesses(arm, goal), arm.exposures || 0);
		var html = '';
		if (arm.isControl) {
			html += '<span class="wpd-exp-sig wpd-exp-sig--baseline">' + escapeHtml(i18n.baseline || 'Baseline') + '</span>';
			html += '<div class="wpd-meta">' + escapeHtml(formatCi(ci.low, ci.high)) + '</div>';
			return html;
		}
		if (!result) {
			return '&mdash;';
		}
		html += '<span class="wpd-exp-sig wpd-exp-sig--' + result.status + '"' + (result.summary ? ' title="' + escapeHtml(result.summary) + '"' : '') + '>' + escapeHtml(result.label) + '</span>';
		if (result.pValue !== null && result.pValue !== undefined) {
			html += '<div class="wpd-meta">' + escapeHtml(formatPValue(result.pValue)) + '</div>';
		}
		html += '<div class="wpd-meta">' + escapeHtml(formatCi(result.rateLow, result.rateHigh)) + '</div>';
		return html;
	}

	function updateSignificance($root, goal, $rows, controlArm) {
		var i18n = (window.wpdAiExperimentsAdmin && wpdAiExperimentsAdmin.i18n) || {};
		var results = [];
		$rows.each(function () {
			var $tr = $(this);
			var arm = readArm($tr);
			var result = null;
			if (!arm.isControl && controlArm) {
				result = significanceResult(
					armSuccesses(controlArm, goal),
					controlArm.exposures || 0,
					armSuccesses(arm, goal),
					arm.exposures || 0
				);
				results.push(result);
			}
			$tr.find('.wpd-exp-cell-significance').html(renderSignificanceHtml(arm, result, goal));
		});

		var $summary = $root.find('.wpd-exp-significance-summary');
		if (!$summary.length) {
			$summary = $('<div class="wpd-exp-significance-summary" />');
			$root.find('.wpd-exp-list-table-scroll').before($summary);
		}

		var chosen = results[0] || null;
		if ($root.find('tr.wpd-exp-arm-row.is-winner').length && results.length) {
			var winnerIndex = $rows.index($root.find('tr.wpd-exp-arm-row.is-winner'));
			var treatmentOffset = 0;
			$rows.each(function (index) {
				if (readArm($(this)).isControl) {
					return;
				}
				if (index === winnerIndex) {
					chosen = results[treatmentOffset];
				}
				treatmentOffset++;
			});
		}

		if (!chosen) {
			$summary.remove();
			return;
		}

		var extra = isCurrencyGoal(goal)
			? '<span class="wpd-meta">' + escapeHtml(i18n.sigValueNote || '') + '</span>'
			: '';
		$summary
			.attr('class', 'wpd-exp-significance-summary wpd-exp-sig-summary--' + chosen.status)
			.html('<strong>' + escapeHtml(chosen.label) + '</strong><span>' + escapeHtml(chosen.summary) + '</span>' + extra);
	}

	function liftVsControl(armRates, controlRates) {
		var points = [];
		var i;
		var last = 0;
		for (i = 0; i < armRates.length; i++) {
			var controlRate = controlRates[i] || 0;
			if (controlRate === 0) {
				points.push(last);
				continue;
			}
			last = ((armRates[i] - controlRate) / Math.abs(controlRate)) * 100;
			points.push(last);
		}
		return points;
	}

	function formatLift(value) {
		var n = Number(value);
		if (!isFinite(n)) {
			n = 0;
		}
		var sign = n > 0 ? '+' : '';
		return sign + formatNumber(n, 1) + '%';
	}

	function formatAxisDate(date) {
		var parts = String(date || '').split('-');
		if (parts.length !== 3) {
			return date;
		}
		var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
		if (isNaN(d.getTime())) {
			return date;
		}
		return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
	}

	function svgEl(name, attrs) {
		var el = document.createElementNS('http://www.w3.org/2000/svg', name);
		var key;
		for (key in attrs) {
			if (Object.prototype.hasOwnProperty.call(attrs, key)) {
				el.setAttribute(key, attrs[key]);
			}
		}
		return el;
	}

	function svgEventPoint(svg, e) {
		var ctm = svg.getScreenCTM();
		if (!ctm) {
			return null;
		}
		var pt = svg.createSVGPoint();
		pt.x = e.clientX;
		pt.y = e.clientY;
		return pt.matrixTransform(ctm.inverse());
	}

	function svgToChartX(svg, $chart, svgX) {
		var ctm = svg.getScreenCTM();
		var chartEl = $chart.get(0);
		if (!ctm || !chartEl) {
			return svgX;
		}
		var pt = svg.createSVGPoint();
		pt.x = svgX;
		pt.y = 0;
		return pt.matrixTransform(ctm).x - chartEl.getBoundingClientRect().left;
	}

	function nearestDateIndex(svgX, xPositions) {
		var best = 0;
		var bestDist = Infinity;
		var i;
		for (i = 0; i < xPositions.length; i++) {
			var dist = Math.abs(xPositions[i] - svgX);
			if (dist < bestDist) {
				bestDist = dist;
				best = i;
			}
		}
		return best;
	}

	function renderProgressChart($root) {
		var $progress = $root.find('.wpd-exp-progress');
		if (!$progress.length) {
			return;
		}
		var data = parseProgress($progress);
		var $chart = $progress.find('.wpd-exp-progress-chart');
		var $legend = $progress.find('.wpd-exp-progress-legend');
		var $metric = $progress.find('.wpd-exp-progress-metric');
		var goal = $root.find('.wpd-exp-goal-select').val() || $root.attr('data-default-goal') || 'transaction';
		var liftLabel =
			(window.wpdAiExperimentsAdmin && wpdAiExperimentsAdmin.i18n && wpdAiExperimentsAdmin.i18n.liftVsControl) ||
			'Lift vs control';
		var objectives = getObjectives();
		var meta = objectives[goal] || {};
		$metric.text(meta.rate_label ? liftLabel + ' · ' + meta.rate_label : liftLabel);
		$chart.empty();
		$legend.empty();

		if (!data || !data.dates || !data.dates.length || !data.arms || !data.arms.length) {
			$chart.append($('<p class="wpd-exp-progress-empty"/>').text(
				(window.wpdAiExperimentsAdmin && wpdAiExperimentsAdmin.i18n && wpdAiExperimentsAdmin.i18n.noChartData) || ''
			));
			return;
		}

		var colors = getProgressColors();
		var ratesByArm = [];
		var controlRates = null;
		$.each(data.arms, function (idx, arm) {
			var rates = cumulativeSeries(arm, goal);
			ratesByArm.push({
				name: arm.name || arm.key,
				isControl: !!arm.is_control,
				rates: rates
			});
			if (arm.is_control) {
				controlRates = rates;
			}
		});
		if (!controlRates && ratesByArm.length) {
			controlRates = ratesByArm[0].rates;
			ratesByArm[0].isControl = true;
		}

		var series = [];
		var yMin = 0;
		var yMax = 0;
		var colorIndex = 0;
		$.each(ratesByArm, function (idx, arm) {
			var points = arm.isControl ? arm.rates.map(function () { return 0; }) : liftVsControl(arm.rates, controlRates || []);
			var i;
			for (i = 0; i < points.length; i++) {
				if (points[i] < yMin) {
					yMin = points[i];
				}
				if (points[i] > yMax) {
					yMax = points[i];
				}
			}
			series.push({
				name: arm.name,
				isControl: arm.isControl,
				color: arm.isControl ? '#64748b' : colors[colorIndex++ % colors.length],
				points: points
			});
		});

		if (yMin === 0 && yMax === 0) {
			yMax = 5;
			yMin = -5;
		} else {
			var padY = Math.max(Math.abs(yMax), Math.abs(yMin)) * 0.12;
			if (yMax > 0) {
				yMax += padY;
			} else {
				yMax = padY;
			}
			if (yMin < 0) {
				yMin -= padY;
			} else {
				yMin = -padY;
			}
		}
		var yRange = yMax - yMin;
		if (yRange <= 0) {
			yRange = 10;
		}

		var width = Math.max($chart.innerWidth() || $progress.innerWidth() || 640, 320);
		var height = 240;
		var pad = { top: 16, right: 20, bottom: 30, left: 72 };
		var innerW = width - pad.left - pad.right;
		var innerH = height - pad.top - pad.bottom;
		var dates = data.dates;
		var lastIndex = Math.max(dates.length - 1, 1);
		var xPositions = [];
		var i;

		function xPos(index) {
			return pad.left + (innerW * index) / lastIndex;
		}
		function yPos(value) {
			return pad.top + innerH - ((value - yMin) / yRange) * innerH;
		}

		for (i = 0; i < dates.length; i++) {
			xPositions.push(xPos(i));
		}

		var svg = svgEl('svg', {
			viewBox: '0 0 ' + width + ' ' + height,
			preserveAspectRatio: 'none',
			class: 'wpd-exp-progress-svg',
			role: 'presentation'
		});

		var ticks = [yMin, yMin / 2, 0, yMax / 2, yMax];
		var seenTicks = {};
		ticks.forEach(function (t) {
			var key = formatLift(t);
			if (seenTicks[key]) {
				return;
			}
			seenTicks[key] = true;
			var y = yPos(t);
			svg.appendChild(svgEl('line', {
				x1: pad.left,
				x2: width - pad.right,
				y1: y,
				y2: y,
				class: t === 0 ? 'wpd-exp-progress-baseline' : 'wpd-exp-progress-grid'
			}));
			var label = svgEl('text', {
				x: pad.left - 8,
				y: y + 4,
				class: 'wpd-exp-progress-axis-label',
				'text-anchor': 'end'
			});
			label.textContent = formatLift(t);
			svg.appendChild(label);
		});

		var xLabels = [0];
		if (dates.length > 2) {
			xLabels.push(Math.floor((dates.length - 1) / 2));
		}
		if (dates.length > 1) {
			xLabels.push(dates.length - 1);
		}
		xLabels.forEach(function (index) {
			var label = svgEl('text', {
				x: xPos(index),
				y: height - 8,
				class: 'wpd-exp-progress-axis-label',
				'text-anchor': index === 0 ? 'start' : index === dates.length - 1 ? 'end' : 'middle'
			});
			label.textContent = formatAxisDate(dates[index]);
			svg.appendChild(label);
		});

		series.forEach(function (arm) {
			var d = '';
			for (i = 0; i < arm.points.length; i++) {
				d += (i === 0 ? 'M' : 'L') + xPos(i) + ' ' + yPos(arm.points[i]) + ' ';
			}
			svg.appendChild(svgEl('path', {
				d: d.trim(),
				fill: 'none',
				stroke: arm.color,
				'stroke-width': arm.isControl ? 1.5 : 2.25,
				'stroke-dasharray': arm.isControl ? '5 4' : 'none',
				'stroke-linejoin': 'round',
				'stroke-linecap': 'round',
				class: 'wpd-exp-progress-line'
			}));
			if (arm.points.length) {
				var last = arm.points.length - 1;
				svg.appendChild(svgEl('circle', {
					cx: xPos(last),
					cy: yPos(arm.points[last]),
					r: 3.5,
					fill: arm.color
				}));
			}
		});

		var hoverLine = svgEl('line', {
			x1: 0,
			x2: 0,
			y1: pad.top,
			y2: height - pad.bottom,
			class: 'wpd-exp-progress-hover-line',
			visibility: 'hidden'
		});
		svg.appendChild(hoverLine);
		svg.appendChild(svgEl('rect', {
			x: 0,
			y: 0,
			width: width,
			height: height,
			fill: 'transparent',
			class: 'wpd-exp-progress-hit'
		}));

		$chart.append(svg);
		var $tooltip = $('<div class="wpd-exp-progress-tooltip" hidden />');
		$chart.append($tooltip);

		series.forEach(function (arm) {
			var $item = $('<span class="wpd-exp-progress-legend-item"/>');
			$item.append($('<i/>').css('background', arm.color));
			$item.append(document.createTextNode(arm.name));
			$legend.append($item);
		});

		function showHover(e) {
			var point = svgEventPoint(svg, e);
			var svgX = point ? point.x : e.offsetX;
			var index = nearestDateIndex(svgX, xPositions);
			var lineX = xPositions[index];
			hoverLine.setAttribute('x1', lineX);
			hoverLine.setAttribute('x2', lineX);
			hoverLine.setAttribute('visibility', 'visible');

			var lines = ['<strong>' + formatAxisDate(dates[index]) + '</strong>'];
			series.forEach(function (arm) {
				lines.push(
					'<span class="wpd-exp-progress-tooltip-row"><i style="background:' +
						arm.color +
						'"></i>' +
						$('<div/>').text(arm.name).html() +
						': ' +
						formatLift(arm.points[index] || 0) +
						'</span>'
				);
			});
			$tooltip.html(lines.join('')).prop('hidden', false);

			var chartWidth = $chart.innerWidth();
			var tipWidth = $tooltip.outerWidth() || 160;
			var left = svgToChartX(svg, $chart, lineX) + 12;
			if (left + tipWidth > chartWidth - 8) {
				left = svgToChartX(svg, $chart, lineX) - tipWidth - 12;
			}
			$tooltip.css({
				left: Math.max(8, left) + 'px',
				top: '16px'
			});
		}

		$(svg)
			.find('.wpd-exp-progress-hit')
			.on('mousemove', showHover)
			.on('mouseleave', function () {
				hoverLine.setAttribute('visibility', 'hidden');
				$tooltip.prop('hidden', true);
			});
	}

	function applyGoal($root, goal) {
		var objectives = getObjectives();
		if (goal === 'purchase_value') {
			goal = 'revenue_per_exposure';
		}
		if (!objectives[goal] || (!isProBuild() && isProObjective(goal))) {
			goal = freeFallbackGoal();
		}

		$root.find('[data-goal-col]').removeClass('is-target-col');
		$root.find('[data-goal-col="' + goal + '"]').addClass('is-target-col');

		var $rows = $root.find('tr.wpd-exp-arm-row');
		var controlRate = null;
		var controlArm = null;
		$rows.each(function () {
			var arm = readArm($(this));
			if (arm.isControl) {
				controlRate = armMetric(arm, goal).rate;
				controlArm = arm;
			}
		});

		var $best = null;
		var bestRate = -Infinity;
		var tied = false;
		$rows.each(function () {
			var $tr = $(this);
			var arm = readArm($tr);
			var metric = armMetric(arm, goal);

			var $lift = $tr.find('.wpd-exp-cell-lift');
			if (arm.isControl || controlRate === null || controlRate === 0) {
				$lift.html('&mdash;');
			} else {
				var lift = ((metric.rate - controlRate) / Math.abs(controlRate)) * 100;
				var cls = lift > 0 ? 'is-up' : lift < 0 ? 'is-down' : '';
				var sign = lift > 0 ? '+' : '';
				$lift.html('<span class="wpd-exp-lift ' + cls + '">' + sign + formatNumber(lift, 1) + '%</span>');
			}

			if (arm.exposures > 0) {
				if (metric.rate > bestRate) {
					bestRate = metric.rate;
					$best = $tr;
					tied = false;
				} else if (metric.rate === bestRate) {
					tied = true;
				}
			}
		});

		$rows.removeClass('is-winner');
		if ($best && !tied) {
			$best.addClass('is-winner');
		}

		updateSignificance($root, goal, $rows, controlArm);
		renderProgressChart($root);
	}

	function initSummaryGoalSwitchers() {
		$('[data-exp-summary]').each(function () {
			var $root = $(this);
			var $select = $root.find('.wpd-exp-goal-select');
			var goal = guardProGoal($select, $select.val() || $root.attr('data-default-goal') || freeFallbackGoal(), true);
			applyGoal($root, goal);
			$select.on('change', function () {
				applyGoal($root, guardProGoal($select, $(this).val()));
			});
		});

		var resizeTimer = null;
		$(window).on('resize', function () {
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(function () {
				$('[data-exp-summary]').each(function () {
					renderProgressChart($(this));
				});
			}, 150);
		});
	}

	function pageRuleNeedsValue(type) {
		return ['path_equals', 'path_contains', 'path_starts_with', 'post_type', 'regex'].indexOf(type) !== -1;
	}

	function syncPageRuleValueField($row) {
		var type = $row.find('select').val();
		var needs = pageRuleNeedsValue(type);
		var $input = $row.find('input[type="text"]');
		$input.prop('disabled', !needs);
		if (!needs) {
			$input.val('').attr('placeholder', '');
		} else if (!$input.attr('placeholder')) {
			$input.attr('placeholder', '/pricing/');
		}
	}

	function syncAllPageRuleValueFields() {
		$('#wpd-exp-page-rules tbody tr').each(function () {
			syncPageRuleValueField($(this));
		});
	}

	function syncPrimaryGoalFields(event) {
		var $type = $('#wpd-exp-primary-goal-type');
		if (!$type.length) {
			return;
		}
		var selected = guardProGoal($type, $type.val(), !event);
		var description = $type.find('option:selected').attr('data-description') || '';
		$type.siblings('.wpd-exp-goal-description').text(description);
		$('.wpd-exp-goal-extra').each(function () {
			var types = (($(this).attr('data-goal-types') || '').split(/\s+/)).filter(Boolean);
			$(this).toggleClass('hidden', types.indexOf(selected) === -1);
		});
	}

	$(function () {
		initEditors(document);
		initSlugPermalink();
		initSummaryGoalSwitchers();
		syncAllPageRuleValueFields();
		syncPrimaryGoalFields();
		$('#wpd-exp-primary-goal-type').on('change', syncPrimaryGoalFields);

		$(document).on('click', '.wpd-exp-code-tab', function (e) {
			e.preventDefault();
			showVariantCodeTab($(this).closest('.wpd-exp-variant'), $(this).attr('data-code-tab'));
		});

		$(document).on('change', '.wpd-exp-is-control', function () {
			if (!this.checked) {
				return;
			}
			$('.wpd-exp-is-control').not(this).prop('checked', false);
		});

		$('#wpd-exp-experiment-form').on('submit', function (e) {
			flushCodeMirrors();
			$('#wpd-exp-page-rules input[type="text"]').prop('disabled', false);
			if (
				window.wpdAiExperimentsAdmin &&
				!window.wpdAiExperimentsAdmin.isPro &&
				window.wpdAiExperimentsAdmin.freeRunningBlocked &&
				$('select[name="experiment[status]"]').val() === 'running'
			) {
				e.preventDefault();
				window.alert(window.wpdAiExperimentsAdmin.i18n.freeRunningLimit || '');
				return false;
			}
		});

		$('#wpd-exp-add-page-rule').on('click', function (e) {
			e.preventDefault();
			var $tbody = $('#wpd-exp-page-rules tbody');
			var $row = $tbody.find('tr').first().clone();
			var index = $tbody.find('tr').length;
			$row.find('select, input').each(function () {
				var name = $(this).attr('name');
				if (name) {
					$(this).attr('name', name.replace(/\[pages\]\[\d+\]/, '[pages][' + index + ']'));
				}
				if ($(this).is('input')) {
					$(this).val('');
					$(this).prop('disabled', false);
				}
			});
			$tbody.append($row);
			syncPageRuleValueField($row);
		});

		$(document).on('change', '#wpd-exp-page-rules select', function () {
			syncPageRuleValueField($(this).closest('tr'));
		});

		$(document).on('click', '.wpd-exp-remove-row', function (e) {
			e.preventDefault();
			var $tbody = $('#wpd-exp-page-rules tbody');
			if ($tbody.find('tr').length < 2) {
				return;
			}
			$(this).closest('tr').remove();
		});

		$('#wpd-exp-add-variant').on('click', function (e) {
			e.preventDefault();
			if (!window.wpdAiExperimentsAdmin || !window.wpdAiExperimentsAdmin.isPro) {
				return;
			}
			flushCodeMirrors();
			var $wrap = $('#wpd-exp-variants');
			var $first = $wrap.find('.wpd-exp-variant').first();
			var index = $wrap.find('.wpd-exp-variant').length;
			var $clone = $first.clone();
			$clone.attr('data-index', index);
			$clone.find('input, textarea').each(function () {
				var name = $(this).attr('name');
				if (name) {
					$(this).attr('name', name.replace(/\[variants\]\[\d+\]/, '[variants][' + index + ']'));
				}
				if ($(this).is('[name*="[id]"]')) {
					$(this).val('0');
				} else if ($(this).is('[name*="[variant_key]"]')) {
					$(this).val('variant_' + index);
				} else if ($(this).is('[name*="[name]"]')) {
					$(this).val((window.wpdAiExperimentsAdmin.i18n.variant || 'Variant') + ' ' + (index + 1));
				} else if ($(this).is('[name*="[weight]"]')) {
					$(this).val('0');
				} else if ($(this).is(':checkbox')) {
					$(this).prop('checked', false);
				} else {
					$(this).val('');
				}
				$(this).removeData('cm');
				$(this).prop('disabled', false);
			});
			$clone.find('.wpd-exp-preview-button').remove();
			$clone.find('.CodeMirror').remove();
			$clone.find('textarea.wpd-exp-code').each(function () {
				$(this).show().css('display', '').removeData('cm');
			});
			$clone.find('.wpd-exp-code-tab').removeClass('is-active has-code').attr('aria-selected', 'false');
			$clone.find('.wpd-exp-code-tab').first().addClass('is-active').attr('aria-selected', 'true');
			$clone.find('.wpd-exp-code-panel').addClass('hidden');
			$clone.find('.wpd-exp-code-panel').first().removeClass('hidden');
			$wrap.append($clone);
			initEditors($clone);
			window.setTimeout(function () {
				refreshVisibleEditors($clone);
			}, 0);
		});
	});
})(jQuery);
