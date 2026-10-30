<?php

declare(strict_types=1);

namespace Chandler\MVC;

use AllowDynamicProperties;
use stdClass;

/**
 * Scope of variables assigned with $this->template->foo = ... by a presenter.
 *
 * Presenters use this object as a bag of template variables; Router reads it
 * via SimplePresenter::getTemplateScope() and passes the values to Latte.
 * Dynamic properties are a feature here, hence the attribute.
 */
#[AllowDynamicProperties]
class TemplateScope extends stdClass {}
