/**
 * MauticAiEnrichment - AI-powered company data enrichment
 */
var MauticAiEnrichment = {

    /**
     * Initialize on page load
     */
    init: function() {
        // Check if we should auto-open the enrichment modal
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('enrich') === 'true') {
            // Find enrichment button and trigger it
            var enrichBtn = mQuery('[data-toggle="ajaxmodal"][href*="enrichment/modal"]');
            if (enrichBtn.length) {
                enrichBtn.click();
            }
        }
    },

    /**
     * Initialize modal event handlers
     */
    initModal: function() {
        // Bind enrichment button clicks
        mQuery('.enrichment-btn').off('click').on('click', function(e) {
            e.preventDefault();
            var btn = mQuery(this);

            if (btn.prop('disabled')) {
                return;
            }

            MauticAiEnrichment.handleEnrichmentClick(btn);
        });

        // Bind save button
        mQuery('#save-enrichment-btn').off('click').on('click', function(e) {
            e.preventDefault();
            MauticAiEnrichment.handleSave(mQuery(this));
        });
    },

    /**
     * Handle enrichment button click
     */
    handleEnrichmentClick: function(btn) {
        var type = btn.data('type');
        var companyId = btn.data('company-id');
        var field = btn.data('field');

        // Get company name from form
        var companyName = mQuery('input[name="company[companyname]"]').val();

        if (!companyName) {
            MauticAiEnrichment.showMessage('Please enter a company name first', 'warning');
            return;
        }

        // Show loading state
        mQuery('#enrichment-results').removeClass('hide');
        mQuery('#enrichment-loading').removeClass('hide');
        mQuery('#enrichment-result-container').addClass('hide');
        mQuery('#enrichment-error').addClass('hide');

        // Store original button HTML and add loading state to clicked button
        var originalHtml = btn.html();
        btn.data('original-html', originalHtml);
        btn.html('<i class="fa fa-spinner fa-spin"></i> Loading...').prop('disabled', true);

        // Disable all other buttons during enrichment
        mQuery('.enrichment-btn').not(btn).prop('disabled', true);

        // Execute enrichment
        MauticAiEnrichment.executeEnrichment(companyId, type, companyName, field, btn);
    },

    /**
     * Execute enrichment via AJAX
     */
    executeEnrichment: function(companyId, type, companyName, field, btn) {
        var url = mauticBaseUrl + 's/companies/enrichment/enrich/' + companyId;

        mQuery.ajax({
            url: url,
            type: 'POST',
            data: {
                type: type,
                companyName: companyName
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    MauticAiEnrichment.displayResult(response, field, companyId);
                } else {
                    MauticAiEnrichment.displayError(response.error || 'Unknown error occurred');
                }
            },
            error: function(xhr) {
                var errorMsg = 'Request failed';
                try {
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    } else if (xhr.statusText) {
                        errorMsg = xhr.statusText;
                    }
                } catch (e) {
                    // Fallback to generic error
                }
                MauticAiEnrichment.displayError(errorMsg);
            },
            complete: function() {
                // Hide loading
                mQuery('#enrichment-loading').addClass('hide');

                // Restore clicked button and re-enable all buttons
                if (btn && btn.data('original-html')) {
                    btn.html(btn.data('original-html'));
                }
                mQuery('.enrichment-btn').prop('disabled', false);
            }
        });
    },

    /**
     * Display enrichment result
     */
    displayResult: function(response, field, companyId) {
        // Populate result table
        mQuery('#result-field-name').text(field);
        mQuery('#result-value').text(response.result);
        mQuery('#result-iterations').text(response.iterations);

        // Set save button data attributes
        mQuery('#save-enrichment-btn')
            .data('field', field)
            .data('value', response.result)
            .data('company-id', companyId);

        // Show result container
        mQuery('#enrichment-result-container').removeClass('hide');
        mQuery('#enrichment-error').addClass('hide');
    },

    /**
     * Display error message
     */
    displayError: function(errorMsg) {
        mQuery('#enrichment-error-message').text(errorMsg);
        mQuery('#enrichment-error').removeClass('hide');
        mQuery('#enrichment-result-container').addClass('hide');
    },

    /**
     * Handle save button click
     */
    handleSave: function(btn) {
        var field = btn.data('field');
        var value = btn.data('value');
        var companyId = btn.data('company-id');

        if (!field || !value) {
            MauticAiEnrichment.showMessage('No data to save', 'warning');
            return;
        }

        if (companyId === 'new') {
            MauticAiEnrichment.showMessage('Please save the company first before enriching', 'warning');
            return;
        }

        // Show loading on button
        var originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        var url = mauticBaseUrl + 's/companies/enrichment/save/' + companyId;

        mQuery.ajax({
            url: url,
            type: 'POST',
            data: {
                field: field,
                value: value
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    MauticAiEnrichment.showMessage('Field updated successfully!', 'success');

                    // Update the form field if it exists
                    var formField = mQuery('input[name="company[' + field + ']"]');
                    if (formField.length) {
                        formField.val(value);
                    }

                    // Hide the enrichment button for this field since it's now filled
                    mQuery('.enrichment-btn[data-field="' + field + '"]').closest('.col-md-4').fadeOut();

                    // Hide result container after successful save
                    mQuery('#enrichment-result-container').fadeOut(function() {
                        mQuery(this).addClass('hide');
                    });

                    // Reset button
                    btn.prop('disabled', false).html(originalHtml);

                    // Check if any buttons are left visible
                    setTimeout(function() {
                        var visibleButtons = mQuery('.enrichment-btn:visible').length;
                        if (visibleButtons === 0) {
                            // Show "all fields filled" message
                            var allFilledMsg = '<div class="alert alert-success">' +
                                '<i class="ri-check-line"></i> ' +
                                '<strong>All fields are now filled!</strong> This company has all available information.' +
                                '</div>';
                            mQuery('.enrichment-modal .row').first().replaceWith(allFilledMsg);
                        }
                    }, 400);

                    // Keep modal open for more enrichments
                    // User can continue enriching other fields
                } else {
                    MauticAiEnrichment.showMessage(response.error || 'Save failed', 'error');
                    btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr) {
                var errorMsg = 'Save failed';
                try {
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMsg = xhr.responseJSON.error;
                    }
                } catch (e) {
                    // Fallback
                }
                MauticAiEnrichment.showMessage(errorMsg, 'error');
                btn.prop('disabled', false).html(originalHtml);
            }
        });
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
    },

    /**
     * Add enrich=true param to URL when modal opens
     */
    updateUrlParam: function() {
        if (window.history && window.history.pushState) {
            var url = new URL(window.location);
            url.searchParams.set('enrich', 'true');
            window.history.pushState({}, '', url);
        }
    },

    /**
     * Remove enrich param from URL when modal closes
     */
    removeUrlParam: function() {
        if (window.history && window.history.pushState) {
            var url = new URL(window.location);
            url.searchParams.delete('enrich');
            window.history.pushState({}, '', url);
        }
    }

};

// Initialize on document ready
mQuery(document).ready(function() {
    MauticAiEnrichment.init();
});
