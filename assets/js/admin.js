/**
 * SCP Easy SMTP - Admin JavaScript
 *
 * Proporciona interactividad a la página de administración del plugin,
 * incluyendo validaciones, animaciones y feedback al usuario.
 *
 * @version 2.1.0
 * @package SCP\EasySMTP\Assets
 */

jQuery(document).ready(function ($) {
    'use strict';

    /**
     * Objeto principal para la funcionalidad del admin de SCP Easy SMTP.
     * @since 2.1.0
     */
    const scpSmtpAdmin = {

        /**
         * Inicializa todos los listeners y funcionalidades.
         */
        init: function () {
            this.listenForToggleSwitch();
            this.listenForCopyPath();
            this.handleTestEmailForm();
            this.setupTemplatesPage();
        },

        /**
         * Gestiona el envío del formulario de prueba de email mediante AJAX.
         */
        handleTestEmailForm: function() {
            $('.scp-test-form').on('submit', function(e) {
                e.preventDefault();

                const form = $(this);
                const feedbackContainer = $('#scp-test-feedback');
                const spinner = form.find('.spinner');
                const email = form.find('#test_email').val();
                const nonce = form.find('#scp_smtp_test_email_nonce').val();

                spinner.addClass('is-active');
                feedbackContainer.slideUp().removeClass('success error');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'scp_smtp_send_test_email',
                        email: email,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            feedbackContainer.text(response.data.message).addClass('success');
                        } else {
                            feedbackContainer.text(response.data.message).addClass('error');
                        }
                    },
                    error: function() {
                        feedbackContainer.text('Error de comunicación con el servidor.').addClass('error');
                    },
                    complete: function() {
                        spinner.removeClass('is-active');
                        feedbackContainer.slideDown();
                    }
                });
            });
        },

        /**
         * Escucha los clics en los interruptores de la tabla de correcciones
         * y envía el formulario automáticamente para guardar el cambio.
         */
        listenForToggleSwitch: function () {
            $('.scp-toggle-switch input[type="checkbox"]').on('change', function () {
                $(this).closest('form').submit();
            });
        },

        /**
         * Escucha los clics en la ruta del archivo de configuración para copiarla.
         */
        listenForCopyPath: function () {
            // Usar delegación de eventos para asegurar que funcione con contenido dinámico
            $(document).on('click', '.scp-config-path code', function () {
                const element = $(this);
                scpSmtpAdmin.copyToClipboard(element.text(), function() {
                    // Callback para feedback visual
                    const originalText = element.text();
                    element.text('¡Copiado!');
                    setTimeout(() => {
                        element.text(originalText);
                    }, 1500);
                });
            });
        },

        /**
         * Configura la página de plantillas y sus interacciones
         */
        setupTemplatesPage: function() {
            // Si no estamos en la pestaña de plantillas, salimos
            if ($('.scp-templates-form').length === 0) {
                return;
            }

            // Efecto de entrada para secciones de idiomas personalizados
            $('.scp-custom-language').each(function(i) {
                var $this = $(this);
                setTimeout(function() {
                    $this.addClass('highlight-animation');
                    setTimeout(function() {
                        $this.removeClass('highlight-animation');
                    }, 1500);
                }, i * 200);
            });

            // Selector de idioma personalizado
            $('#new_lang_select').on('change', function() {
                var selected = this.options[this.selectedIndex];
                $('#new_lang_code').val(selected.value || '');
                $('#new_lang_name').val(selected.getAttribute('data-name') || '');
                $('#new_lang_flag').val(selected.getAttribute('data-flag') || '');
                $('#new_lang_locales').val(selected.getAttribute('data-locales') || '');
            });

            // Confirmación al eliminar idiomas
            $(document).on('click', 'button[name="scp_smtp_remove_language"]', function(e) {
                if (!confirm('¿Estás seguro de que quieres eliminar este idioma? Esta acción no se puede deshacer.')) {
                    e.preventDefault();
                }
            });
            
            // Comprobación de campos requeridos al añadir idiomas
            $('button[name="scp_smtp_add_language"]').on('click', function(e) {
                var code = $('#new_lang_code').val();
                var name = $('#new_lang_name').val();
                
                if (!code || !name) {
                    alert('Por favor, selecciona un idioma de la lista o completa al menos el código y nombre.');
                    e.preventDefault();
                }
                
                // Si todo está bien, mostrar feedback visual
                $(this).addClass('adding-language');
                $(this).html('<span class="dashicons dashicons-update spin"></span> Añadiendo...');
            });
            
            // Si tenemos editores TinyMCE en la página, reinicializarlos para asegurarnos de que funcionan correctamente
            if (typeof tinymce !== 'undefined') {
                setTimeout(function() {
                    // Dar un poco de tiempo para que la página se renderice completamente
                    tinymce.remove();
                    tinymce.init({
                        selector: 'textarea.wp-editor-area',
                        height: 250,
                        menubar: false,
                        plugins: 'lists link paste',
                        toolbar: 'bold italic bullist numlist link removeformat code'
                    });
                }, 500);
            }
        },
        
        /**
         * Copia un texto al portapapeles usando la API moderna.
         * @param {string} text - El texto a copiar.
         * @param {Function} callback - Función a ejecutar en caso de éxito.
         */
        copyToClipboard: function (text, callback) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    if (callback) {
                        callback();
                    }
                }).catch(function(err) {
                    console.error('SCP SMTP: No se pudo copiar el texto: ', err);
                });
            } else {
                // Fallback para navegadores muy antiguos
                try {
                    const textArea = document.createElement("textarea");
                    textArea.value = text;
                    textArea.style.position = "fixed"; // Evitar scroll
                    textArea.style.opacity = "0";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    document.execCommand('copy');
                    if (callback) {
                        callback();
                    }
                } catch (err) {
                    console.error('SCP SMTP: Fallback de copiado falló: ', err);
                } finally {
                    if (textArea) {
                        document.body.removeChild(textArea);
                    }
                }
            }
        }
    };

    // Iniciar el script.
    scpSmtpAdmin.init();

});

