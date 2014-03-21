<?php

/**
 * @file
 * Contains \Drupal\redirect\EventSubscriber\RedirectSubscriber.
 */

namespace Drupal\redirect\EventSubscriber;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Routing\UrlGenerator;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Redirect subscriber for controller requests.
 */
class RedirectSubscriber implements EventSubscriberInterface {

  protected $urlGenerator;
  protected $manager;

  /**
   * Constructs a \Drupal\redirect\EventSubscriber\RedirectSubscriber object.
   *
   * @param \Drupal\Core\Routing\UrlGenerator $url_generator
   *   The sensor runner service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $manager
   *   The entity storage controller class.
   */
  public function __construct(UrlGenerator $url_generator, EntityManagerInterface $manager) {
    $this->urlGenerator = $url_generator;
    $this->manager = $manager;
  }

  /**
   * Returns the site maintenance page if the site is offline.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The event to process.
   */
  public function onKernelRequestCheckRedirect(GetResponseEvent $event) {
    $request = $event->getRequest();

    // Get URL info and process it to be used for hash generation.
    parse_str($request->getQueryString(), $query);
    $path = ltrim($request->getPathInfo(), '/');
    $hash_lang_not_specified = Redirect::generateHash($path, $query, Language::LANGCODE_NOT_SPECIFIED);
    $hash_lang_current = Redirect::generateHash($path, $query, Language::LANGCODE_DEFAULT);

    // Load redirects by hash.
    $redirects = $this->manager->getStorageController('redirect')->loadByProperties(array('hash' => array($hash_lang_not_specified, $hash_lang_current)));

    // If any redirects found do the redirect response.
    if (!empty($redirects)) {
      /** @var \Drupal\redirect\Entity\Redirect $redirect */
      $redirect = reset($redirects);
      // Handle internal path.
      if ($route_name = $redirect->getRedirectRouteName()) {
        $url = $this->urlGenerator->generateFromRoute($route_name, $redirect->getRedirectRouteParameters(), array(
          'absolute' => TRUE,
          'query' => $redirect->getRedirectOption('query'),
        ));
      }
      // Handle external path.
      else {
        $url = $redirect->getRedirectUrl();
      }
      $response = new RedirectResponse($url, $redirect->getStatusCode());
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('onKernelRequestCheckRedirect', 50);
    return $events;
  }
}
