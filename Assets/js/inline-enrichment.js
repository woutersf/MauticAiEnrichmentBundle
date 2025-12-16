/**
 * Inline AI Enrichment - Add autofill links next to company fields
 */
var MauticInlineEnrichment = {

    fieldConfig: {
        'companyemail': {
            label: 'Email',
            icon: 'ri-mail-line',
            type: 'email'
        },
        'companywebsite': {
            label: 'Website',
            icon: 'ri-global-line',
            type: 'website'
        },
        'companyaddress1': {
            label: 'Address',
            icon: 'ri-map-pin-line',
            type: 'address'
        },
        'companyphone': {
            label: 'Phone',
            icon: 'ri-phone-line',
            type: 'phone'
        },
        'companynumber_of_employees': {
            label: 'Employees',
            icon: 'ri-team-line',
            type: 'employees'
        },
        'companydescription': {
            label: 'Description',
            icon: 'ri-file-text-line',
            type: 'description'
        }
    },

    /**
     * Initialize inline enrichment
     */
    init: function() {
        // Only run on company edit/new pages
        if (!this.isCompanyPage()) {
            return;
        }

        // Add autofill links to empty fields
        this.addAutofillLinks();
    },

    /**
     * Check if we're on a company page
     */
    isCompanyPage: function() {
        return window.location.href.indexOf('/companies/') > -1;
    },

    /**
     * Add autofill links next to empty fields
     */
    addAutofillLinks: function() {
        var self = this;

        mQuery.each(this.fieldConfig, function(fieldName, config) {
            // Try to find input or textarea field
            var field = mQuery('input[name="company[' + fieldName + ']"]');
            if (!field.length) {
                field = mQuery('textarea[name="company[' + fieldName + ']"]');
            }

            if (field.length) {
                // Find the label for this field
                var label = field.closest('.form-group').find('label').first();

                if (label.length && label.find('.autofill-link').length === 0) {
                    // Add autofill link after label
                    var link = mQuery('<a href="javascript:void(0)" class="autofill-link" style="margin-left: 1em; font-size: 0.9em; color: #486AE2; display: none;" data-enrichment-key="' + fieldName + '">' +
                        'Autofill ✨' +
                        '</a>');

                    label.append(link);

                    // Bind click handler
                    link.on('click', function(e) {
                        e.preventDefault();
                        self.handleAutofillClick(fieldName, config);
                    });
                }

                // Show/hide link based on field value
                self.updateAutofillLinkVisibility(fieldName);

                // Add field listeners to show/hide autofill link
                field.on('blur keyup change', function() {
                    self.updateAutofillLinkVisibility(fieldName);
                });
            }
        });
    },

    /**
     * Update autofill link visibility based on field value
     */
    updateAutofillLinkVisibility: function(fieldName) {
        // Try input or textarea field
        var field = mQuery('input[name="company[' + fieldName + ']"]');
        if (!field.length) {
            field = mQuery('textarea[name="company[' + fieldName + ']"]');
        }

        var link = mQuery('.autofill-link[data-enrichment-key="' + fieldName + '"]');

        if (field.length && link.length) {
            if (!field.val() || field.val().trim() === '') {
                link.fadeIn();
            } else {
                link.fadeOut();
            }
        }
    },

    /**
     * Handle autofill link click
     */
    handleAutofillClick: function(fieldName, config) {
        // Get company name
        var companyName = mQuery('input[name="company[companyname]"]').val();

        if (!companyName || companyName.trim() === '') {
            this.showMessage('Please enter a company name first', 'warning');
            return;
        }

        // Get company ID from URL
        var companyId = this.getCompanyIdFromUrl();

        // Show modal with loader
        this.showOptionsModal(config.label, true);

        // Make AJAX call
        this.fetchEnrichmentOptions(companyId, config.type, companyName, fieldName);
    },

    /**
     * Get company ID from URL
     */
    getCompanyIdFromUrl: function() {
        var match = window.location.pathname.match(/\/companies\/edit\/(\d+)/);
        return match ? match[1] : 'new';
    },

    /**
     * Show options modal
     */
    showOptionsModal: function(fieldLabel, showLoader) {
        // Remove existing modal if any
        mQuery('#inline-enrichment-modal').remove();

        var modalHtml = '<div class="modal fade" id="inline-enrichment-modal" tabindex="-1">' +
            '<div class="modal-dialog">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>' +
            '<h4 class="modal-title">AI Autofill - ' + fieldLabel + '</h4>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div id="enrichment-options-loader" class="text-center" style="padding: 40px;">' +
            '<i class="fa fa-spinner fa-spin fa-3x" style="color: #486AE2;"></i>' +
            '<p style="margin-top: 20px;">AI is searching for options...</p>' +
            '</div>' +
            '<div id="enrichment-options-content" class="hide"></div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        mQuery('body').append(modalHtml);
        mQuery('#inline-enrichment-modal').modal('show');
    },

    /**
     * Fetch enrichment options via AJAX
     */
    fetchEnrichmentOptions: function(companyId, enrichmentType, companyName, fieldName) {
        var self = this;
        var url = mauticBaseUrl + 's/companies/enrichment/options/' + companyId;

        mQuery.ajax({
            url: url,
            type: 'POST',
            data: {
                type: enrichmentType,
                companyName: companyName
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.options) {
                    self.displayOptions(response.options, fieldName);
                } else {
                    self.displayError(response.error || 'No options found');
                }
            },
            error: function(xhr) {
                var errorMsg = 'Request failed';
                try {
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    }
                } catch (e) {
                    // Fallback
                }
                self.displayError(errorMsg);
            }
        });
    },

    /**
     * Display options as clickable buttons
     */
    displayOptions: function(optionsString, fieldName) {
        // Parse CSV-style options (handles quoted strings with commas inside)
        var options = this.parseCSVOptions(optionsString);

        if (options.length === 0) {
            this.displayError('No options found');
            return;
        }

        // Check if AI returned UNKNOWN (filter out UNKNOWN options)
        var validOptions = options.filter(function(opt) {
            return opt.toUpperCase() !== 'UNKNOWN';
        });

        if (validOptions.length === 0) {
            // All options were UNKNOWN
            this.displayError('Information not found.');
            return;
        }

        // Use only valid options
        options = validOptions;

        var html = '<div class="enrichment-options">';
        html += '<p class="text-muted mb-md">Select an option to fill the field:</p>';

        mQuery.each(options, function(index, option) {
            html += '<button class="btn btn-primary btn-lg btn-block mb-sm enrichment-option-btn" ' +
                'data-value="' + option.replace(/"/g, '&quot;') + '" ' +
                'data-field="' + fieldName + '" ' +
                'style="text-align: left; padding: 15px; font-size: 16px; white-space: normal; word-break: break-word;">' +
                '<i class="ri-check-line"></i> ' + option +
                '</button>';
        });

        html += '</div>';

        // Hide loader and show options
        mQuery('#enrichment-options-loader').addClass('hide');
        mQuery('#enrichment-options-content').html(html).removeClass('hide');

        // Bind click handlers
        var self = this;
        mQuery('.enrichment-option-btn').on('click', function() {
            var value = mQuery(this).data('value');
            var field = mQuery(this).data('field');
            self.fillField(field, value);
        });
    },

    /**
     * Parse CSV-style options (handles quoted strings with commas)
     */
    parseCSVOptions: function(csvString) {
        var options = [];
        var current = '';
        var inQuotes = false;

        for (var i = 0; i < csvString.length; i++) {
            var char = csvString[i];

            if (char === '"') {
                inQuotes = !inQuotes;
                // Don't add the quote character itself
            } else if (char === ',' && !inQuotes) {
                // End of option
                var trimmed = current.trim();
                if (trimmed.length > 0) {
                    options.push(trimmed);
                }
                current = '';
            } else {
                current += char;
            }
        }

        // Add last option
        var trimmed = current.trim();
        if (trimmed.length > 0) {
            options.push(trimmed);
        }

        return options;
    },

    /**
     * Display error message
     */
    displayError: function(errorMsg) {
        var html = '<div class="alert alert-danger">' +
            '<i class="ri-error-warning-line"></i> ' +
            '<strong>Error:</strong> ' + errorMsg +
            '</div>';

        mQuery('#enrichment-options-loader').addClass('hide');
        mQuery('#enrichment-options-content').html(html).removeClass('hide');
    },

    /**
     * Fill field with selected value
     */
    fillField: function(fieldName, value) {
        // Special handling for address field - parse structured format
        if (fieldName === 'companyaddress1') {
            this.fillAddressFields(value);
        } else {
            var field = mQuery('input[name="company[' + fieldName + ']"]');

            if (field.length) {
                field.val(value);

                // Trigger change event (this will also update autofill link visibility)
                field.trigger('change');
            }
        }

        // Show success message
        this.showMessage('Field filled successfully!', 'success');

        // Close modal
        mQuery('#inline-enrichment-modal').modal('hide');
    },

    /**
     * Parse and fill structured address data
     * Handles both formats:
     * - Comma format: "Street, PostalCode City, Country"
     * - Slash format: "Country / Zip code / City / Address 1 / Address 2"
     */
    fillAddressFields: function(addressString) {
        // Check which format we're dealing with
        if (addressString.indexOf(' / ') > -1) {
            // Slash format: "Country / Zip code / City / Address 1 / Address 2"
            this.fillAddressFieldsSlashFormat(addressString);
        } else {
            // Comma format: "Street, PostalCode City, Country"
            this.fillAddressFieldsCommaFormat(addressString);
        }
    },

    /**
     * Fill address fields from slash format
     * Format: "Country / Zip code / City / Address 1 / Address 2"
     */
    fillAddressFieldsSlashFormat: function(addressString) {
        var parts = addressString.split(' / ').map(function(part) {
            return part.trim();
        });

        // Map to fields: [Country, Zip, City, Address1, Address2]
        var fieldMapping = [
            { index: 0, field: 'companycountry' },
            { index: 1, field: 'companyzipcode' },
            { index: 2, field: 'companycity' },
            { index: 3, field: 'companyaddress1' },
            { index: 4, field: 'companyaddress2' }
        ];

        var self = this;
        mQuery.each(fieldMapping, function(i, mapping) {
            if (parts[mapping.index]) {
                self.fillSingleField(mapping.field, parts[mapping.index]);
            }
        });
    },

    /**
     * Fill address fields from comma format
     * Format: "Street, PostalCode City, Country"
     * Example: "Moutstraat 60, 9000 Ghent, Belgium"
     */
    fillAddressFieldsCommaFormat: function(addressString) {
        var parts = addressString.split(',').map(function(part) {
            return part.trim();
        });

        // Extract components
        var street = parts[0] || '';
        var postalCity = parts[1] || '';
        var country = parts[2] || '';

        // Parse "PostalCode City" into separate components
        var postalCode = '';
        var city = '';
        if (postalCity) {
            var postalCityMatch = postalCity.match(/^(\d+)\s+(.+)$/);
            if (postalCityMatch) {
                postalCode = postalCityMatch[1];
                city = postalCityMatch[2];
            } else {
                // If no postal code found, treat entire string as city
                city = postalCity;
            }
        }

        // Fill fields
        var fields = {
            'companyaddress1': street,
            'companyzipcode': postalCode,
            'companycity': city,
            'companycountry': country
        };

        var self = this;
        mQuery.each(fields, function(fieldName, value) {
            if (value) {
                self.fillSingleField(fieldName, value);
            }
        });
    },

    /**
     * Fill a single field (handles input, select, and textarea fields)
     */
    fillSingleField: function(fieldName, value) {
        // Try input field first
        var field = mQuery('input[name="company[' + fieldName + ']"]');

        if (field.length) {
            field.val(value);
            field.trigger('change');
            return;
        }

        // Try textarea field (for description)
        field = mQuery('textarea[name="company[' + fieldName + ']"]');

        if (field.length) {
            field.val(value);
            field.trigger('change');
            return;
        }

        // Try select field (for dropdowns like country)
        field = mQuery('select[name="company[' + fieldName + ']"]');

        if (field.length) {
            field.val(value);
            field.trigger('change');

            // Trigger Chosen update if it's a Chosen dropdown
            field.trigger('chosen:updated');
        }
    },

    /**
     * Show flash message
     */
    showMessage: function(message, type) {
        var alertClass = 'alert-growl--error';
        if (type === 'success') {
            alertClass = 'alert-growl--success';
        } else if (type === 'warning') {
            alertClass = 'alert-growl--warning';
        }

        var alert = '<div class="alert alert-growl ' + alertClass + ' alert-new">' +
            '<button type="button" class="close" data-dismiss="alert" aria-hidden="true" aria-label="Close">' +
            '<i class="ri-close-line"></i>' +
            '</button>' +
            '<span>' + message + '</span>' +
            '</div>';

        mQuery('#flashes').append(alert);

        // Auto-close after 4 seconds
        mQuery('#flashes .alert-new').each(function() {
            var me = this;
            window.setTimeout(function() {
                mQuery(me).fadeTo(500, 0).slideUp(500, function() {
                    mQuery(this).remove();
                });
            }, 4000);
            mQuery(this).removeClass('alert-new');
        });
    }
};

// Initialize on document ready
mQuery(document).ready(function() {
    MauticInlineEnrichment.init();
});
