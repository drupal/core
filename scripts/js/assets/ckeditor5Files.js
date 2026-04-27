/**
 * @file
 * Callback returning the list of files to copy to the assets/vendor directory.
 */
const { globSync } = require('glob');

/**
 * Build the list of assets to be copied based on what exists in the filesystem.
 *
 * @param {string} packageFolder
 *   The path to node_modules folder.
 *
 * @return {DrupalLibraryAsset[]}
 *  List of libraries and files to process.
 */
module.exports = (packageFolder) => {
  const ckeditor5Package = `${packageFolder}/ckeditor5`;

  // Collect translation files from dist/translations/.
  const translationFiles = globSync(
    `${ckeditor5Package}/dist/translations/*.umd.js`,
    { nodir: true },
  ).map((absolutePath) => ({
    from: absolutePath.replace(`${ckeditor5Package}/`, ''),
    to: `translations/${absolutePath.replace(/.*\//, '')}`,
  }));

  return [
    {
      pack: 'ckeditor5',
      library: 'ckeditor5',
      folder: 'ckeditor5',
      files: [
        { from: 'dist/browser/ckeditor5.umd.js', to: 'ckeditor5.umd.js' },
        { from: 'dist/browser/ckeditor5.umd.js.map', to: 'ckeditor5.umd.js.map' },
        { from: 'dist/browser/ckeditor5.css', to: 'ckeditor5.css' },
        ...translationFiles,
      ],
    },
  ];
};
