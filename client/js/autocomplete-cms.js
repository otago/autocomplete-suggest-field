/**
 * jQuery entwine implementation of a SearchableDropdownField with AJAX support.
 * This replaces the React version for SilverStripe CMS usage.
 */
(function ($) {

    $('.ss-autocomplete-dropdown-field').entwine({
        onmatch: function () {
            const $field = $(this).find('.autocompletesuggest');
            const schema = $field.data('schema') || {};
            const optionUrl = schema.optionUrl || $field.data('option-url') || '';
            const multi = schema.multi;
            const placeholder = $field.attr('placeholder') || '';
            const initialLabel = schema.value?.label ?? '';

            if (!$field.find('.autocomplete-suggest-field__input').length) {
                $field.append(
                    $(`<div class="autocomplete-suggest-field__container">
                            <div class="autocomplete-suggest-field__inner-container">
                                <div class="autocomplete-suggest-field__control">
                                    <input type="text" class="autocomplete-suggest-field__input" aria-autocomplete="list"
                                     aria-haspopup="listbox" aria-expanded="false" autocomplete="off" value="${initialLabel}" />
                                </div>
                            </div>
                        </div>`)
                );
            }

            const $input = $field.find('.autocomplete-suggest-field__input');
            const $control = $field.find('.autocomplete-suggest-field__control');

            $input.off('focus blur');
            $input.on('focus', function () {
                $control.addClass('autocomplete-suggest-field__control--focused');
                $input.attr('aria-expanded', 'true');
            });
            $input.on('blur', function () {
                $control.removeClass('autocomplete-suggest-field__control--focused');
                $input.attr('aria-expanded', 'false');
            });
            $control.on('click', function () {
                $input.focus();
            });

            // Create close button SVG
            const $closeBtn = $(`
                <button type="button" class="autocomplete-suggest-field__clear-btn" style="display:none;" aria-label="Clear">
                    <svg height="20" width="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false" class="react-select-8mmkcg">
                        <path d="M14.348 14.849c-0.469 0.469-1.229 0.469-1.697 0l-2.651-3.030-2.651 3.029c-0.469 0.469-1.229 0.469-1.697 0-0.469-0.469-0.469-1.229 0-1.697l2.758-3.15-2.759-3.152c-0.469-0.469-0.469-1.228 0-1.697s1.228-0.469 1.697 0l2.652 3.031 2.651-3.031c0.469-0.469 1.228-0.469 1.697 0s0.469 1.229 0 1.697l-2.758 3.152 2.758 3.15c0.469 0.469 0.469 1.229 0 1.698z"></path>
                    </svg>
                </button>
            `);
            $control.append($closeBtn);

            // the loading ellipses when ajaxing requests
            const $loading = $(`
                        <div class="ss-autocomplete-loading">
                            <span class="ss-autocomplete-loading-dot"></span>
                            <span class="ss-autocomplete-loading-dot"></span>
                            <span class="ss-autocomplete-loading-dot"></span>
                        </div>
                    `);
            $field.append($loading);
            $loading.hide();

            // Helper: fetch options via AJAX
            let fetchCache = {};
            let currentRequest = null; // Track the current AJAX request

            // dropdown container
            const $dropdown = $('<div class="ss-autocomplete-dropdown ss-autocomplete-dropdown--hidden" role="listbox" tabindex="-1"></div>').insertAfter($field);

            // when to show/hide the loading ellipses and close button
            function toggleInputState() {
                if (currentRequest && currentRequest.readyState !== 4) {
                    $loading.show();
                    $closeBtn.hide();
                } else {
                    $loading.hide();
                    if (!hasNoInputValue()) {
                        $closeBtn.show();
                    } else {
                        $closeBtn.hide();
                    }
                }
                if (multi) {
                    $closeBtn.hide();
                }
                if (!$field.find('.ss-autocomplete-option').length) {
                    $dropdown.addClass('ss-autocomplete-dropdown--hidden');
                    $input.attr('aria-expanded', 'false');
                }
            }
            toggleInputState();

            // Handle close button click
            $closeBtn.on('click', function (e) {
                e.preventDefault();
                nullifyInput();
                $input.val('');
                $input.trigger('input');
                $input.focus();
            });

            if (!initialLabel) {
                nullifyInput();
            }

            // populate default value if it exists
            function populateDefaultValue() {
                if (multi) {
                    if (Array.isArray(schema.value)) {
                        schema.value.forEach(function (item) {
                            $('<input type="hidden" />')
                                .attr('name', $field.attr('name') + '')
                                .attr('data-value', item.value)
                                .val(JSON.stringify({ label: item.label, value: item.value }))
                                .appendTo($field.parent());
                        });
                        renderMultiOptions();
                    }
                } else {
                    if (schema.value) {
                        $('<input type="hidden" />')
                            .attr('name', $field.attr('name') + '')
                            .val(JSON.stringify({ label: schema.value.label, value: schema.value.value }))
                            .appendTo($field.parent());
                    }
                }
            }
            populateDefaultValue();

            function fetchOptions(term) {
                if (!term) {
                    if (fetchCache.hasOwnProperty('')) {
                        return $.Deferred().resolve(fetchCache['']).promise();
                    }
                    fetchCache[''] = [];
                    return $.Deferred().resolve(fetchCache['']).promise();
                }
                $dropdown.empty();
                $dropdown.addClass('ss-autocomplete-dropdown--hidden');
                if (fetchCache.hasOwnProperty(term)) {
                    return $.Deferred().resolve(fetchCache[term]).promise();
                }
                let url = optionUrl;
                if (term) {
                    url += (url.indexOf('?') === -1 ? '?' : '&') + 'term=' + encodeURIComponent(term);
                }
                if (currentRequest && currentRequest.readyState !== 4) {
                    currentRequest.abort();
                }
                currentRequest = $.ajax({
                    url: url,
                    method: 'GET',
                    headers: {
                        'X-SecurityID': $('input[name=SecurityID]').val() || ''
                    },
                    dataType: 'json'
                });
                return currentRequest.then(function (data) {
                    fetchCache[term] = data;
                    toggleInputState();
                    return data;
                }).catch(function () {
                    toggleInputState();
                    return [];
                });
            }

            // Helper: render dropdown options
            function renderOptions(options) {
                $dropdown.removeClass('ss-autocomplete-dropdown--hidden');
                $dropdown.empty();
                if (!options.length) {
                    let state = 'No matching options';
                    if (currentRequest && currentRequest.readyState !== 4) {
                        state = 'Loading...';
                    }
                    $dropdown
                        .append(
                            $('<div class="ss-autocomplete-no-options" role="option" aria-disabled="true"></div>').text(state)
                        );
                    $input.attr('aria-activedescendant', '');
                    return;
                }
                options.forEach(function (opt, idx) {
                    const $opt = $('<div class="ss-autocomplete-option" role="option" tabindex="-1"></div>')
                        .text(opt.label)
                        .attr('data-value', opt.value)
                        .attr('id', 'ss-autocomplete-option-' + idx);


                    if (keyExists(opt.value)) {
                        $opt.addClass('ss-autocomplete-option--exists');
                    }

                    $dropdown.append($opt);
                });
                highlightOption(0); // Always highlight first option by default
            }

            // Helper: close dropdown
            function closeDropdown() {
                $dropdown.addClass('ss-autocomplete-dropdown--hidden');
                $input.attr('aria-expanded', 'false');
                $input.attr('aria-activedescendant', '');
            }

            // Helper: does one of these keys allready exist as a hidden value
            function keyExists(val) {
                if (multi) {
                    $items = $field.siblings('input[type=hidden][name="' + $field.attr('name') + '"]');
                    for (let i = 0; i < $items.length; i++) {
                        let jsondata = $items.eq(i).val();
                        let label = JSON.parse(jsondata);
                        if (label.value === val) {
                            return true;
                        }
                    }
                }
                return false;
            }

            // render the multi value selector options
            function renderMultiOptions() {
                if (!multi) {
                    return;
                }

                // clear search
                $input.val('');
                $control.find('.autocomplete-suggest-field__multi-pill').remove();
                let $hiddenfields = $field.siblings('input[type=hidden][name="' + $field.attr('name') + '"]');
                for (let i = 0; i < $hiddenfields.length; i++) {
                    let jsondata = $hiddenfields.eq(i).val();
                    let label = JSON.parse(jsondata);
                    let $multiPillButton = $(`
                        <div class="autocomplete-suggest-field__multi-pill">
                            <span class="autocomplete-suggest-field__multi-pill-label">${label.label}</span>
                        </div>`);
                    let $multiCcloseBtn = $(`
                        <button type="button" class="autocomplete-suggest-field__clear-btn-pill" aria-label="Clear">
                            <svg height="20" width="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false" class="react-select-8mmkcg">
                                <path d="M14.348 14.849c-0.469 0.469-1.229 0.469-1.697 0l-2.651-3.030-2.651 3.029c-0.469 0.469-1.229 0.469-1.697 0-0.469-0.469-0.469-1.229 0-1.697l2.758-3.15-2.759-3.152c-0.469-0.469-0.469-1.228 0-1.697s1.228-0.469 1.697 0l2.652 3.031 2.651-3.031c0.469-0.469 1.228-0.469 1.697 0s0.469 1.229 0 1.697l-2.758 3.152 2.758 3.15c0.469 0.469 0.469 1.229 0 1.698z"></path>
                            </svg>
                        </button>  `);

                    $multiCcloseBtn.on('click', function (e) {
                        e.preventDefault();
                        $multiPillButton.remove();
                        $field.siblings('input[type=hidden][name="' + $field.attr('name') + '"][data-value="' + label.value + '"]').remove();
                    });
                    $multiPillButton.append($multiCcloseBtn);
                    $control.prepend($multiPillButton);
                }
            }

            // Helper: highlight option by index
            function highlightOption(idx) {
                const $options = $dropdown.find('.ss-autocomplete-option');
                $options.removeClass('ss-autocomplete-option--highlighted').attr('aria-selected', 'false');
                if ($options.length && idx >= 0 && idx < $options.length) {
                    $options.eq(idx).addClass('ss-autocomplete-option--highlighted').attr('aria-selected', 'true');
                    $input.attr('aria-activedescendant', $options.eq(idx).attr('id'));
                    // Scroll into view if needed
                    let optionEl = $options.get(idx);
                    if (optionEl && optionEl.scrollIntoView) {
                        optionEl.scrollIntoView({ block: 'nearest' });
                    }
                } else {
                    $input.attr('aria-activedescendant', '');
                }
            }

            // Helper: get highlighted option index
            function getHighlightedIndex() {
                const $options = $dropdown.find('.ss-autocomplete-option');
                return $options.index($dropdown.find('.ss-autocomplete-option--highlighted'));
            }

            // Helper: select highlighted option
            function selectHighlightedOption() {
                const idx = getHighlightedIndex();
                const $options = $dropdown.find('.ss-autocomplete-option');
                if (idx >= 0 && idx < $options.length) {
                    $options.eq(idx).trigger('mousedown'); // Use mousedown to avoid blur
                }
            }

            // Handle input for searching
            $field.on('input', function () {
                const term = $field.find('input').val();
                if (!term) {
                    toggleInputState();
                    $dropdown.empty();
                    closeDropdown();
                    return;
                }
                fetchOptions(term).then(function (options) {
                    renderOptions(options);
                });
                toggleInputState();
                resizeInputField();
            });


            // make sure the input field doesn't go too small, and we have things inline
            function resizeInputField() {
                // Create a hidden span to measure text width
                let $span = $field.find('.autocomplete-suggest-field__sizer');
                if (!$span.length) {
                    $span = $('<span class="autocomplete-suggest-field__sizer" style="position:absolute;visibility:hidden;height:0;white-space:pre;"></span>');
                    $field.append($span);
                }
                // Copy font styles for accurate measurement
                $span.css({
                    'font': $input.css('font'),
                    'font-size': $input.css('font-size'),
                    'font-family': $input.css('font-family'),
                    'font-weight': $input.css('font-weight'),
                    'letter-spacing': $input.css('letter-spacing'),
                    'padding': $input.css('padding'),
                    'border': $input.css('border'),
                });
                // Set span text to input value or placeholder
                $span.text($input.val() || $input.attr('placeholder') || '');
                // Add a little extra space for caret
                $input.width($span.width() + 20);
            }


            // Handle input for clearing
            function nullifyInput() {
                if (multi) {
                    return;
                }
                let $hidden = $field.siblings('input[type=hidden][name="' + $field.attr('name') + '"]');

                if (!$hidden.length) {
                    $hidden = $('<input type="hidden" />')
                        .attr('name', $field.attr('name'))
                        .appendTo($field.parent());
                }

                $hidden.val(JSON.stringify({ label: '', value: null }));
            }

            // Helper: check if input has no value
            function hasNoInputValue() {
                if (!$input.val()) {
                    return true;
                }

                if (JSON.stringify($input.val()) === JSON.stringify('')) {
                    return true;
                }
                return false;
            }



            // Handle option selection
            $field.parent().on('mousedown', '.ss-autocomplete-option', function (e) {
                // Use mousedown instead of click to avoid blur before selection
                e.preventDefault();
                const value = $(this).data('value');
                const label = $(this).text();

                // If the option already exists, do not add it again
                if ($(this).hasClass('ss-autocomplete-option--exists')) {
                    closeDropdown();
                    return;
                }
                $input.val(label);
                resizeInputField();

                if (multi) {
                    $hidden = $('<input type="hidden" />')
                        .attr('name', $field.attr('name') + '')
                        .appendTo($field.parent());
                    $hidden.val(JSON.stringify({ label: label, value: value }));
                    renderMultiOptions();
                } else {
                    $field.val(label);
                    let $hidden = $field.siblings('input[type=hidden][name="' + $field.attr('name') + '"]');
                    if (!$hidden.length) {
                        $hidden = $('<input type="hidden" />')
                            .attr('name', $field.attr('name'))
                            .appendTo($field.parent());
                    }
                    $hidden.val(JSON.stringify({ label: label, value: value }));
                }
                closeDropdown();
                $field.trigger('change');
            });

            // Keyboard navigation
            $input.on('keydown', function (e) {
                const $options = $dropdown.find('.ss-autocomplete-option');
                let idx = getHighlightedIndex();
                if ($dropdown.hasClass('ss-autocomplete-dropdown--hidden') || !$options.length) return;

                if (e.key === 'ArrowDown' || e.keyCode === 40) {
                    e.preventDefault();
                    if (idx < $options.length - 1) {
                        highlightOption(idx + 1);
                    } else {
                        highlightOption(0);
                    }
                } else if (e.key === 'ArrowUp' || e.keyCode === 38) {
                    e.preventDefault();
                    if (idx > 0) {
                        highlightOption(idx - 1);
                    } else {
                        highlightOption($options.length - 1);
                    }
                } else if (e.key === 'Enter' || e.keyCode === 13) {
                    if (idx >= 0 && idx < $options.length) {
                        e.preventDefault();
                        selectHighlightedOption();
                    }
                } else if (e.key === 'Escape' || e.keyCode === 27) {
                    closeDropdown();
                }
            });

            // Mouse hover highlights
            $dropdown.on('mousemove', '.ss-autocomplete-option', function () {
                const $options = $dropdown.find('.ss-autocomplete-option');
                $options.removeClass('ss-autocomplete-option--highlighted').attr('aria-selected', 'false');
                $(this).addClass('ss-autocomplete-option--highlighted').attr('aria-selected', 'true');
                $input.attr('aria-activedescendant', $(this).attr('id'));
            });

            // Hide dropdown on blur
            $input.on('blur', function () {
                setTimeout(closeDropdown, 200);
            });

            // Initial placeholder and accessibility
            $field.attr('placeholder', placeholder);
            $input.attr('placeholder', placeholder);
            $input.attr('role', 'combobox');
            const dropdownId = 'autocomplete-dropdown-' + Math.random().toString(36).slice(2, 11);
            $dropdown.attr('id', dropdownId);
            $input.attr('aria-controls', dropdownId);
        }
    });
})(jQuery);

