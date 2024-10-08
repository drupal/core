<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\Extension\Dependency;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Validation\Plugin\Validation\Constraint\RegexConstraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotIdenticalTo;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 *   This API is experimental.
 */
final class Recipe {

  const COMPOSER_PROJECT_TYPE = 'drupal-recipe';

  public function __construct(
    public readonly string $name,
    public readonly string $description,
    public readonly string $type,
    public readonly RecipeConfigurator $recipes,
    public readonly InstallConfigurator $install,
    public readonly ConfigConfigurator $config,
    public readonly Finder $content,
    public readonly string $path,
  ) {
  }

  /**
   * Creates a recipe object from the provided path.
   *
   * @param string $path
   *   The path to a recipe.
   *
   * @return static
   *   The Recipe object.
   */
  public static function createFromDirectory(string $path): static {
    $recipe_data = self::parse($path . '/recipe.yml');

    $recipes = new RecipeConfigurator(is_array($recipe_data['recipes']) ? $recipe_data['recipes'] : [], dirname($path));
    $install = new InstallConfigurator($recipe_data['install'], \Drupal::service('extension.list.module'), \Drupal::service('extension.list.theme'));
    $config = new ConfigConfigurator($recipe_data['config'], $path, \Drupal::service('config.storage'));
    $content = new Finder($path . '/content');
    return new static($recipe_data['name'], $recipe_data['description'], $recipe_data['type'], $recipes, $install, $config, $content, $path);
  }

  /**
   * Parses and validates a recipe.yml file.
   *
   * @param string $file
   *   The path of a recipe.yml file.
   *
   * @return mixed[]
   *   The parsed and validated data from the file.
   *
   * @throws \Drupal\Core\Recipe\RecipeFileException
   *   Thrown if the recipe.yml file is unreadable, invalid, or cannot be
   *   validated.
   */
  private static function parse(string $file): array {
    if (!file_exists($file)) {
      throw new RecipeFileException($file, "There is no $file file");
    }
    $recipe_contents = file_get_contents($file);
    if (!$recipe_contents) {
      throw new RecipeFileException($file, "$file does not exist or could not be read.");
    }
    // Certain parts of our validation need to be able to scan for other
    // recipes.
    // @see ::validateRecipeExists()
    // @see ::validateConfigActions()
    $include_path = dirname($file, 2);

    $constraints = new Collection([
      'name' => new Required([
        new Type('string'),
        new NotBlank(),
        // Matching `type: label` in core.data_types.schema.yml.
        new RegexConstraint(
          pattern: '/([^\PC])/u',
          message: 'Recipe names cannot span multiple lines or contain control characters.',
          match: FALSE,
        ),
      ]),
      'description' => new Optional([
        new NotBlank(),
        // Matching `type: text` in core.data_types.schema.yml.
        new RegexConstraint(
          pattern: '/([^\PC\x09\x0a\x0d])/u',
          message: 'The recipe description cannot contain control characters, only visible characters.',
          match: FALSE,
        ),
      ]),
      'type' => new Optional([
        new Type('string'),
        new NotBlank(),
        // Matching `type: label` in core.data_types.schema.yml.
        new RegexConstraint(
          pattern: '/([^\PC])/u',
          message: 'Recipe type cannot span multiple lines or contain control characters.',
          match: FALSE,
        ),
      ]),
      'recipes' => new Optional([
        new All([
          new Type('string'),
          new NotBlank(),
          // If recipe depends on itself, ::validateRecipeExists() will set off
          // an infinite loop. We can avoid that by skipping that validation if
          // the recipe depends on itself, which is what Sequentially does.
          new Sequentially([
            new NotIdenticalTo(
              value: basename(dirname($file)),
              message: 'The {{ compared_value }} recipe cannot depend on itself.',
            ),
            new Callback(
              callback: self::validateRecipeExists(...),
              payload: $include_path,
            ),
          ]),
        ]),
      ]),
      // @todo https://www.drupal.org/i/3424603 Validate the corresponding
      //   import.
      'install' => new Optional([
        new All([
          new Type('string'),
          new Sequentially([
            new NotBlank(),
            new Callback(self::validateExtensionIsAvailable(...)),
          ]),
        ]),
      ]),
      'config' => new Optional([
        new Collection([
          // Each entry in the `import` list can either be `*` (import all of
          // the extension's config), or a list of config names to import from
          // the extension.
          // @todo https://www.drupal.org/i/3439716 Validate config file name,
          //   if given.
          'import' => new Optional([
            new All([
              new AtLeastOneOf([
                new IdenticalTo('*'),
                new All([
                  new Type('string'),
                  new NotBlank(),
                  new Regex('/^.+\./'),
                ]),
              ]),
            ]),
          ]),
          'actions' => new Optional([
            new All([
              new Type('array'),
              new NotBlank(),
              new Callback(
                callback: self::validateConfigActions(...),
                payload: $include_path,
              ),
            ]),
          ]),
        ]),
      ]),
      'content' => new Optional([
        new Type('array'),
      ]),
    ]);

    $recipe_data = Yaml::decode($recipe_contents);
    /** @var \Symfony\Component\Validator\ConstraintViolationList $violations */
    $violations = Validation::createValidator()->validate($recipe_data, $constraints);
    if (count($violations) > 0) {
      throw RecipeFileException::fromViolationList($file, $violations);
    }
    $recipe_data += [
      'description' => '',
      'type' => '',
      'recipes' => [],
      'install' => [],
      'config' => [],
      'content' => [],
    ];
    return $recipe_data;
  }

  /**
   * Validates that the value is an available module/theme (installed or not).
   *
   * @param string $value
   *   The value to validate.
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validator execution context.
   *
   * @see \Drupal\Core\Extension\ExtensionList::getAllAvailableInfo()
   */
  private static function validateExtensionIsAvailable(string $value, ExecutionContextInterface $context): void {
    $name = Dependency::createFromString($value)->getName();
    $all_available = \Drupal::service(ModuleExtensionList::class)->getAllAvailableInfo() + \Drupal::service(ThemeExtensionList::class)->getAllAvailableInfo();
    if (!array_key_exists($name, $all_available)) {
      $context->addViolation('"%extension" is not a known module or theme.', [
        '%extension' => $name,
      ]);
    }
  }

  /**
   * Validates that a recipe exists.
   *
   * @param string $name
   *   The machine name of the recipe to look for.
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validator execution context.
   * @param string $include_path
   *   The recipe's include path.
   */
  private static function validateRecipeExists(string $name, ExecutionContextInterface $context, string $include_path): void {
    if (empty($name)) {
      return;
    }
    try {
      RecipeConfigurator::getIncludedRecipe($include_path, $name);
    }
    catch (UnknownRecipeException) {
      $context->addViolation('The %name recipe does not exist.', ['%name' => $name]);
    }
  }

  /**
   * Validates that the corresponding extension is enabled for a config action.
   *
   * @param mixed $value
   *   The config action; not used.
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validator execution context.
   * @param string $include_path
   *   The recipe's include path.
   */
  private static function validateConfigActions(mixed $value, ExecutionContextInterface $context, string $include_path): void {
    $config_name = str_replace(['[config][actions]', '[', ']'], '', $context->getPropertyPath());
    [$config_provider] = explode('.', $config_name);
    if ($config_provider === 'core') {
      return;
    }

    $recipe_being_validated = $context->getRoot();
    assert(is_array($recipe_being_validated));

    $configurator = new RecipeConfigurator($recipe_being_validated['recipes'] ?? [], $include_path);

    /** @var \Drupal\Core\Extension\ModuleExtensionList $module_list */
    $module_list = \Drupal::service('extension.list.module');
    // The config provider must either be an already-installed module or theme,
    // or an extension being installed by this recipe or a recipe it depends on.
    $all_extensions = [
      ...array_keys($module_list->getAllInstalledInfo()),
      ...array_keys(\Drupal::service('extension.list.theme')->getAllInstalledInfo()),
      ...$recipe_being_validated['install'] ?? [],
      ...$configurator->listAllExtensions(),
    ];
    // Explicitly treat required modules as installed, even if Drupal isn't
    // installed yet, because we know they WILL be installed.
    foreach ($module_list->getAllAvailableInfo() as $name => $info) {
      if (!empty($info['required'])) {
        $all_extensions[] = $name;
      }
    }

    if (!in_array($config_provider, $all_extensions, TRUE)) {
      $context->addViolation('Config actions cannot be applied to %config_name because the %config_provider extension is not installed, and is not installed by this recipe or any of the recipes it depends on.', [
        '%config_name' => $config_name,
        '%config_provider' => $config_provider,
      ]);
    }
  }

}
