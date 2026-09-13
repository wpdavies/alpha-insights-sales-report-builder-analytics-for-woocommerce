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
		if (!objectives[goal]) {
			goal = 'transaction';
		}
		var meta = objectives[goal] || {};
		if (meta.rate_label) {
			$root.find('.wpd-exp-col-goal-rate').text(meta.rate_label);
		}

		$root.find('[data-goal-col]').removeClass('is-target-col');
		$root.find('[data-goal-col="' + goal + '"]').addClass('is-target-col');

		var $rows = $root.find('tr.wpd-exp-arm-row');
		var controlRate = null;
		$rows.each(function () {
			var arm = readArm($(this));
			if (arm.isControl) {
				controlRate = armMetric(arm, goal).rate;
			}
		});

		var $best = null;
		var bestRate = -Infinity;
		var tied = false;
		$rows.each(function () {
			var $tr = $(this);
			var arm = readArm($tr);
			var metric = armMetric(arm, goal);
			var displayRate =
				goal === 'aov' || goal === 'purchase_value' || goal === 'revenue_per_exposure'
					? formatMoney(metric.rate)
					: formatNumber(metric.rate * 100, 1) + '%';
			$tr.find('.wpd-exp-cell-goal-rate').text(displayRate);

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

		renderProgressChart($root);
	}

	function initSummaryGoalSwitchers() {
		$('[data-exp-summary]').each(function () {
			var $root = $(this);
			var $select = $root.find('.wpd-exp-goal-select');
			var goal = $select.val() || $root.attr('data-default-goal') || 'transaction';
			applyGoal($root, goal);
			$select.on('change', function () {
				applyGoal($root, $(this).val());
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

	function syncPrimaryGoalFields() {
		var $type = $('#wpd-exp-primary-goal-type');
		if (!$type.length) {
			return;
		}
		var selected = $type.val();
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
