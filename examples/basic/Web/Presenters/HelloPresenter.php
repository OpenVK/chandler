<?php

declare(strict_types=1);

namespace helloapp\Web\Presenters;

/*
 * HelloPresenter — example Presenter
 *
 * A Presenter is the MVC Controller. It receives the request (via
 * method parameters named after route placeholders), sets template
 * variables via $this->template, and Latte renders the matching
 * template automatically.
 *
 * Method naming convention:
 *   Route handler "Hello->index" → method renderIndex()
 *   Route handler "Hello->greet" → method renderGreet()
 *
 * Each render* method corresponds to a template at:
 *   Web/Presenters/templates/Hello/{Method}.latte
 * (without the "render" prefix and with Latte extension).
 *
 * Presenter lifecycle (managed by Router):
 *   onStartup() → render*() → onBeforeRender() → [render template]
 *   → onAfterRender() → onStop() → onDestruction()
 *
 * All Presenters must extend SimplePresenter (which implements
 * IPresenter) and live under {namespace}\Web\Presenters.
 */

use Chandler\MVC\SimplePresenter;

final class HelloPresenter extends SimplePresenter
{
    /**
     * renderIndex — homepage
     *
     * Corresponds to route: GET /
     * Template:              Web/Presenters/templates/Hello/Index.latte
     *
     * Sets $message from the app config (HELLOAPP_ROOT_CONF)
     * so that changing the greeting doesn't require code edits.
     */
    public function renderIndex(): void
    {
        // HELLOAPP_ROOT_CONF is defined by ExtensionManager from
        // helloapp.yml. It contains the raw parsed YAML of the
        // entire file — we access the helloapp: section.
        $this->template->message = HELLOAPP_ROOT_CONF["helloapp"]["message"]
                                 ?? "Hello from Chandler!";
    }

    /**
     * renderGreet — personalized greeting
     *
     * Corresponds to route: GET /hello/{text}
     * Template:              Web/Presenters/templates/Hello/Greet.latte
     *
     * @param string|int $name  Captured from the {text} placeholder in the URL.
     *                          Type hinting works because the router passes
     *                          numeric values as int, everything else as string.
     */
    public function renderGreet(int|string $name): void
    {
        $this->template->name  = $name;
        $this->template->title = "Hello, $name";
    }
}
