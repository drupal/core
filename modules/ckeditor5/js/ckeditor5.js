/**
* DO NOT EDIT THIS FILE.
* See the following change record for more information,
* https://www.drupal.org/node/2815083
* @preserve
**/

function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); if (enumerableOnly) { symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; }); } keys.push.apply(keys, symbols); } return keys; }

function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i] != null ? arguments[i] : {}; if (i % 2) { ownKeys(Object(source), true).forEach(function (key) { _defineProperty(target, key, source[key]); }); } else if (Object.getOwnPropertyDescriptors) { Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)); } else { ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } } return target; }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }

function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _iterableToArrayLimit(arr, i) { var _i = arr == null ? null : typeof Symbol !== "undefined" && arr[Symbol.iterator] || arr["@@iterator"]; if (_i == null) return; var _arr = []; var _n = true; var _d = false; var _s, _e; try { for (_i = _i.call(arr); !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"] != null) _i["return"](); } finally { if (_d) throw _e; } } return _arr; }

function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }

function _typeof(obj) { "@babel/helpers - typeof"; if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") { _typeof = function _typeof(obj) { return typeof obj; }; } else { _typeof = function _typeof(obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }; } return _typeof(obj); }

function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }

function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }

function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }

function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && iter[Symbol.iterator] != null || iter["@@iterator"] != null) return Array.from(iter); }

function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }

function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }

(function (Drupal, debounce, CKEditor5, $) {
  Drupal.CKEditor5Instances = new Map();
  var callbacks = new Map();
  var required = new Set();

  function findFunc(scope, name) {
    if (!scope) {
      return null;
    }

    var parts = name.includes('.') ? name.split('.') : name;

    if (parts.length > 1) {
      return findFunc(scope[parts.shift()], parts);
    }

    return typeof scope[parts[0]] === 'function' ? scope[parts[0]] : null;
  }

  function buildFunc(config) {
    var func = config.func;
    var fn = findFunc(window, func.name);

    if (typeof fn === 'function') {
      var result = func.invoke ? fn.apply(void 0, _toConsumableArray(func.args)) : fn;
      return result;
    }

    return null;
  }

  function buildRegexp(config) {
    var pattern = config.regexp.pattern;
    var main = pattern.match(/\/(.+)\/.*/)[1];
    var options = pattern.match(/\/.+\/(.*)/)[1];
    return new RegExp(main, options);
  }

  function processConfig(config) {
    function processArray(config) {
      return config.map(function (item) {
        if (_typeof(item) === 'object') {
          return processConfig(item);
        }

        return item;
      });
    }

    return Object.entries(config).reduce(function (processed, _ref) {
      var _ref2 = _slicedToArray(_ref, 2),
          key = _ref2[0],
          value = _ref2[1];

      if (_typeof(value) === 'object') {
        if (value.hasOwnProperty('func')) {
          processed[key] = buildFunc(value);
        } else if (value.hasOwnProperty('regexp')) {
          processed[key] = buildRegexp(value);
        } else if (Array.isArray(value)) {
          processed[key] = processArray(value);
        } else {
          processed[key] = processConfig(value);
        }
      } else {
        processed[key] = value;
      }

      return processed;
    }, {});
  }

  var setElementId = function setElementId(element) {
    var id = Math.random().toString().slice(2, 9);
    element.setAttribute('data-ckeditor5-id', id);
    return id;
  };

  var getElementId = function getElementId(element) {
    return element.getAttribute('data-ckeditor5-id');
  };

  function selectPlugins(plugins) {
    return plugins.map(function (pluginDefinition) {
      var _pluginDefinition$spl = pluginDefinition.split('.'),
          _pluginDefinition$spl2 = _slicedToArray(_pluginDefinition$spl, 2),
          build = _pluginDefinition$spl2[0],
          name = _pluginDefinition$spl2[1];

      if (CKEditor5[build] && CKEditor5[build][name]) {
        return CKEditor5[build][name];
      }

      console.warn("Failed to load ".concat(build, " - ").concat(name));
      return null;
    });
  }

  var offCanvasCss = function offCanvasCss(element) {
    element.parentNode.setAttribute('data-drupal-ck-style-fence', true);

    if (!document.querySelector('#ckeditor5-off-canvas-reset')) {
      var prefix = "#drupal-off-canvas [data-drupal-ck-style-fence]";
      var existingCss = '';

      _toConsumableArray(document.styleSheets).forEach(function (sheet) {
        if (!sheet.href || sheet.href && sheet.href.indexOf('off-canvas') === -1) {
          try {
            var rules = sheet.cssRules;

            _toConsumableArray(rules).forEach(function (rule) {
              var cssText = rule.cssText;
              var selector = rule.cssText.split('{')[0];
              cssText = cssText.replace(selector, selector.replace(/,/g, ", ".concat(prefix)));
              existingCss += "".concat(prefix, " ").concat(cssText);
            });
          } catch (e) {
            console.warn("Stylesheet ".concat(sheet.href, " not included in CKEditor reset due to the browser's CORS policy."));
          }
        }
      });

      var addedCss = ["".concat(prefix, " .ck.ck-content {display:block;min-height:5rem;}"), "".concat(prefix, " .ck.ck-content * {display:initial;background:initial;color:initial;padding:initial;}"), "".concat(prefix, " .ck.ck-content li {display:list-item}"), "".concat(prefix, " .ck.ck-content ol li {list-style-type: decimal}"), "".concat(prefix, " .ck[contenteditable], ").concat(prefix, " .ck[contenteditable] * {-webkit-user-modify: read-write;-moz-user-modify: read-write;}")];
      var blockSelectors = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ol', 'ul', 'address', 'article', 'aside', 'blockquote', 'body', 'dd', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'header', 'hgroup', 'hr', 'html', 'legend', 'main', 'menu', 'pre', 'section', 'xmp'].map(function (blockElement) {
        return "".concat(prefix, " .ck.ck-content ").concat(blockElement);
      }).join(', \n');
      var blockCss = "".concat(blockSelectors, " { display: block; }");
      var prefixedCss = [].concat(addedCss, [existingCss, blockCss]).join('\n');

      var _offCanvasCss = document.createElement('style');

      _offCanvasCss.innerHTML = prefixedCss;

      _offCanvasCss.setAttribute('id', 'ckeditor5-off-canvas-reset');

      document.body.appendChild(_offCanvasCss);
    }
  };

  Drupal.editors.ckeditor5 = {
    attach: function attach(element, format) {
      var editorClassic = CKEditor5.editorClassic;
      var _format$editorSetting = format.editorSettings,
          toolbar = _format$editorSetting.toolbar,
          plugins = _format$editorSetting.plugins,
          pluginConfig = _format$editorSetting.config,
          language = _format$editorSetting.language;
      var extraPlugins = selectPlugins(plugins);

      var config = _objectSpread({
        extraPlugins: extraPlugins,
        toolbar: toolbar,
        language: language
      }, processConfig(pluginConfig));

      var id = setElementId(element);
      var ClassicEditor = editorClassic.ClassicEditor;
      ClassicEditor.create(element, config).then(function (editor) {
        Drupal.CKEditor5Instances.set(id, editor);

        if (element.hasAttribute('required')) {
          required.add(id);
          element.removeAttribute('required');
        }

        editor.model.document.on('change:data', function () {
          var callback = callbacks.get(id);

          if (callback) {
            if (editor.plugins.has('SourceEditing')) {
              if (editor.plugins.get('SourceEditing').isSourceEditingMode) {
                callback();
                return;
              }
            }

            debounce(callback, 400)();
          }
        });
        var isOffCanvas = element.closest('#drupal-off-canvas');

        if (isOffCanvas) {
          offCanvasCss(element);
        }
      }).catch(function (error) {
        console.error(error);
      });
    },
    detach: function detach(element, format, trigger) {
      var id = getElementId(element);
      var editor = Drupal.CKEditor5Instances.get(id);

      if (!editor) {
        return;
      }

      if (trigger === 'serialize') {
        editor.updateSourceElement();
      } else {
        element.removeAttribute('contentEditable');
        var textElement = null;
        var originalValue = null;
        var usingQuickEdit = (((Drupal || {}).quickedit || {}).editors || {}).editor;

        if (usingQuickEdit) {
          Drupal.quickedit.editors.editor.prototype.revert = function revertQuickeditChanges() {
            textElement = this.$textElement[0];
            originalValue = this.model.get('originalValue');
          };
        }

        editor.destroy().then(function () {
          if (textElement && originalValue) {
            textElement.innerHTML = originalValue;
          }

          Drupal.CKEditor5Instances.delete(id);
          callbacks.delete(id);

          if (required.has(id)) {
            element.setAttribute('required', 'required');
            required.delete(id);
          }
        }).catch(function (error) {
          console.error(error);
        });
      }
    },
    onChange: function onChange(element, callback) {
      callbacks.set(getElementId(element), callback);
    },
    attachInlineEditor: function attachInlineEditor(element, format, mainToolbarId) {
      var editorDecoupled = CKEditor5.editorDecoupled;
      var _format$editorSetting2 = format.editorSettings,
          toolbar = _format$editorSetting2.toolbar,
          plugins = _format$editorSetting2.plugins,
          pluginConfig = _format$editorSetting2.config,
          language = _format$editorSetting2.language;
      var extraPlugins = selectPlugins(plugins);

      var config = _objectSpread({
        extraPlugins: extraPlugins,
        toolbar: toolbar,
        language: language
      }, processConfig(pluginConfig));

      var id = setElementId(element);
      var DecoupledEditor = editorDecoupled.DecoupledEditor;
      DecoupledEditor.create(element, config).then(function (editor) {
        Drupal.CKEditor5Instances.set(id, editor);
        var toolbar = document.getElementById(mainToolbarId);
        toolbar.appendChild(editor.ui.view.toolbar.element);
        editor.model.document.on('change:data', function () {
          var callback = callbacks.get(id);

          if (callback) {
            debounce(callback, 400)(editor.getData());
          }
        });
      }).catch(function (error) {
        console.error(error);
      });
    }
  };
  Drupal.ckeditor5 = {
    saveCallback: null,
    openDialog: function openDialog(url, saveCallback, dialogSettings) {
      var classes = dialogSettings.dialogClass ? dialogSettings.dialogClass.split(' ') : [];
      classes.push('ui-dialog--narrow');
      dialogSettings.dialogClass = classes.join(' ');
      dialogSettings.autoResize = window.matchMedia('(min-width: 600px)').matches;
      dialogSettings.width = 'auto';
      var $content = $("<div class=\"ckeditor5-dialog-loading\"><span style=\"top: -40px;\" class=\"ckeditor5-dialog-loading-link\">".concat(Drupal.t('Loading...'), "</span></div>"));
      $content.appendTo($('body'));
      var ckeditorAjaxDialog = Drupal.ajax({
        dialog: dialogSettings,
        dialogType: 'modal',
        selector: '.ckeditor5-dialog-loading-link',
        url: url,
        progress: {
          type: 'throbber'
        },
        submit: {
          editor_object: {}
        }
      });
      ckeditorAjaxDialog.execute();
      window.setTimeout(function () {
        $content.find('span').animate({
          top: '0px'
        });
      }, 1000);
      Drupal.ckeditor5.saveCallback = saveCallback;
    }
  };

  function redirectTextareaFragmentToCKEditor5Instance() {
    var hash = window.location.hash.substr(1);
    var element = document.getElementById(hash);

    if (element) {
      var editorID = getElementId(element);
      var editor = Drupal.CKEditor5Instances.get(editorID);

      if (editor) {
        editor.sourceElement.nextElementSibling.setAttribute('id', "cke_".concat(hash));
        window.location.replace("#cke_".concat(hash));
      }
    }
  }

  $(window).on('hashchange.ckeditor', redirectTextareaFragmentToCKEditor5Instance);
  $(window).on('dialog:beforecreate', function () {
    $('.ckeditor5-dialog-loading').animate({
      top: '-40px'
    }, function removeDialogLoading() {
      $(this).remove();
    });
  });
  $(window).on('editor:dialogsave', function (e, values) {
    if (Drupal.ckeditor5.saveCallback) {
      Drupal.ckeditor5.saveCallback(values);
    }
  });
  $(window).on('dialog:afterclose', function () {
    if (Drupal.ckeditor5.saveCallback) {
      Drupal.ckeditor5.saveCallback = null;
    }
  });
})(Drupal, Drupal.debounce, CKEditor5, jQuery);