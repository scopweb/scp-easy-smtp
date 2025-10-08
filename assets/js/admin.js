/**
 * SCP Easy SMTP - Admin JavaScript
 * Interactive features for the admin interface
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Auto-dismiss notices after 5 seconds
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut(400, function() {
                $(this).remove();
            });
        }, 5000);

        // Email validation with visual feedback
        $('#test_email').on('input', function() {
            const email = $(this).val();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (emailRegex.test(email)) {
                $(this).css('border-color', '#46b450');
            } else if (email.length > 0) {
                $(this).css('border-color', '#dc3232');
            } else {
                $(this).css('border-color', '#e0e0e0');
            }
        });

        // Test email form submission with loading state
        $('.scp-test-form').on('submit', function() {
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalText = $submitBtn.html();

            $submitBtn.prop('disabled', true)
                     .html('<span class="dashicons dashicons-update spin"></span> Enviando...');

            // Re-enable after a timeout in case of error
            setTimeout(function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }, 10000);
        });

        // Add spinning animation to dashicons
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .dashicons.spin {
                animation: spin 1s linear infinite;
            }
        `;
        document.head.appendChild(style);

        // Highlight config path on click
        $('.scp-config-path code').on('click', function() {
            const range = document.createRange();
            range.selectNodeContents(this);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);

            // Show copied message
            const $message = $('<span class="copied-message">Copiado!</span>');
            $(this).parent().append($message);

            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 2000);
        });

        // Add copy button style
        const copyStyle = document.createElement('style');
        copyStyle.textContent = `
            .scp-config-path {
                position: relative;
            }
            .scp-config-path code {
                cursor: pointer;
                user-select: all;
            }
            .scp-config-path code:hover {
                background: #1e2a38;
            }
            .copied-message {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: #46b450;
                color: white;
                padding: 5px 10px;
                border-radius: 3px;
                font-size: 12px;
                animation: slideIn 0.3s ease;
            }
        `;
        document.head.appendChild(copyStyle);

        // Table row highlighting
        $('.scp-table tbody tr').hover(
            function() {
                $(this).css('background-color', '#f0f8ff');
            },
            function() {
                $(this).css('background-color', '');
            }
        );

        // Tooltip for domain corrections
        $('.scp-badge').attr('title', 'Número de correcciones realizadas');

        // Animate stats on page load
        $('.scp-stat-value').each(function() {
            const $this = $(this);
            const finalValue = parseInt($this.text());

            if (finalValue > 0) {
                $({ value: 0 }).animate({ value: finalValue }, {
                    duration: 1500,
                    easing: 'swing',
                    step: function() {
                        $this.text(Math.floor(this.value));
                    },
                    complete: function() {
                        $this.text(finalValue);
                    }
                });
            }
        });

        // Console easter egg
        console.log('%c SCP Easy SMTP v2.0 ', 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 16px; padding: 10px; border-radius: 5px;');
        console.log('Plugin desarrollado por ScopWeb - https://scopweb.com');
    });

})(jQuery);
