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
