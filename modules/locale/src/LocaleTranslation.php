<?php

namespace Drupal\locale;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\Translator\TranslatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * String translator using the locale module.
 *
 * Full featured translation system using locale's string storage and
 * database caching.
 */
class LocaleTranslation implements TranslatorInterface, DestructableInterface {

  use DependencySerializationTrait {
    __sleep as traitSleep;
  }

  /**
   * Cached translations.
   *
   * @var array
   *   Array of \Drupal\locale\LocaleLookup objects indexed by language code
   *   and context.
   */
  protected $translations = [];

  /**
   * The translate english configuration value.
   *
   * @var bool
   */
  protected $translateEnglish;

  public function __construct(
    protected StringStorageInterface $storage,
    #[Autowire(service: 'cache.default')]
    protected CacheBackendInterface $cache,
    #[Autowire(service: 'lock')]
    protected LockBackendInterface $lock,
    protected ConfigFactoryInterface $configFactory,
    protected LanguageManagerInterface $languageManager,
    protected RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getStringTranslation($langcode, $string, $context) {
    // If the language is not suitable for locale module, just return.
    if ($langcode == LanguageInterface::LANGCODE_SYSTEM || ($langcode == 'en' && !$this->canTranslateEnglish())) {
      return FALSE;
    }
    // Strings are cached by langcode, context and roles, using instances of the
    // LocaleLookup class to handle string lookup and caching.
    if (!isset($this->translations[$langcode][$context])) {
      $this->translations[$langcode][$context] = new LocaleLookup($langcode, $context, $this->storage, $this->cache, $this->lock, $this->configFactory, $this->languageManager, $this->requestStack);
    }
    $translation = $this->translations[$langcode][$context]->get($string);
    // If the translation is TRUE, no translation exists, but that string needs
    // to be stored in the persistent cache for performance reasons (so for
    // example, we don't have hundreds of queries to locale tables on each
    // request). That cache is persisted when the request ends, and the lookup
    // service is destroyed.
    return $translation === TRUE ? FALSE : $translation;
  }

  /**
   * Gets translate english configuration value.
   *
   * @return bool
   *   TRUE if english should be translated, FALSE if not.
   */
  protected function canTranslateEnglish() {
    if (!isset($this->translateEnglish)) {
      $this->translateEnglish = $this->configFactory->get('locale.settings')->get('translate_english');
    }
    return $this->translateEnglish;
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    unset($this->translateEnglish);
    $this->translations = [];
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    foreach ($this->translations as $context) {
      foreach ($context as $lookup) {
        if ($lookup instanceof DestructableInterface) {
          $lookup->destruct();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    // ::$translations is an array of LocaleLookup objects, which have the
    // database service injected and therefore cannot be serialized safely.
    return array_diff($this->traitSleep(), ['translations']);
  }

}
