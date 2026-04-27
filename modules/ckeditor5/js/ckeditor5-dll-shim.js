/**
 * @file
 * Backward-compatibility shim for CKEditor 5 DLL-built plugins.
 *
 * @deprecated in drupal:12.0.0 and is removed from drupal:13.0.0. Contributed
 *   modules should rebuild their CKEditor 5 plugins using webpack externals
 *   instead of DllReferencePlugin.
 *
 * @see https://www.drupal.org/node/3581531
 */
((Drupal) => {
  if (typeof CKEDITOR === 'undefined') {
    return;
  }

  window.CKEditor5 = window.CKEditor5 || {};
  // The DLL function is called with paths like "./src/core.js". All paths
  // resolve to the same CKEDITOR global since the UMD build is flat.
  window.CKEditor5.dll = function () {
    Drupal.deprecationError({
      message:
        'CKEditor5.dll() is deprecated in drupal:12.0.0 and is removed from drupal:13.0.0. Rebuild your CKEditor 5 plugin using webpack externals instead of DllReferencePlugin. See https://www.drupal.org/node/3581531',
    });
    return CKEDITOR;
  };
})(Drupal);
