<?php declare(strict_types=1);
use Chandler\Security\Authenticator;

return (function(): ?bool
{
    $auth = Authenticator::i();
    $user = $auth->getUser();
    if(!$user) return NULL;
    
    return $user->can("access")->model("admin")->whichBelongsTo(NULL);
});