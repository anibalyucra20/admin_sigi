/*!
 * App JS para SIGI
 * Este fichero arranca todos los módulos de la interfaz:
 * - MetisMenu para el sidebar
 * - SimpleBar para scroll custom
 * - Waves para efectos de clicks
 * - Función de “back to top”
 */

!function(window, document, $) {
    'use strict';
  
    var App = {
      init: function() {
        this.initMetisMenu();
        this.initSimpleBar();
        this.initWaves();
        this.initBackToTop();
      },
  
      // Inicializa el menú lateral
      initMetisMenu: function() {
        $('#side-menu').metisMenu();
      },
  
      // Plugins de scroll custom en sidebar
      initSimpleBar: function() {
        $('[data-simplebar]').each(function() {
          new SimpleBar(this);
        });
      },
  
      // Efecto “olas” al pulsar botones
      initWaves: function() {
        if (typeof Waves !== 'undefined') {
          Waves.init();
          Waves.attach('.btn');
        }
      },
  
      // Botón de “volver arriba”
      initBackToTop: function() {
        var btn = $('#back-to-top');
        $(window).scroll(function() {
          if ($(this).scrollTop() > 100) {
            btn.fadeIn();
          } else {
            btn.fadeOut();
          }
        });
        btn.on('click', function() {
          $('html, body').animate({scrollTop: 0}, 600);
          return false;
        });
      }
    };
  
    // Arranca la app cuando el DOM esté listo
    $(document).ready(function() {
      App.init();
    });
  
  }(window, document, jQuery);
  