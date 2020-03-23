<?php
namespace exface\Core\CommonLogic\Security\Authorization;

use exface\Core\Interfaces\Security\AuthorizationPointInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\Security\AuthorizationPolicyInterface;
use exface\Core\DataTypes\PolicyEffectDataType;
use exface\Core\DataTypes\PolicyCombiningAlgorithmDataType;
use exface\Core\Interfaces\UserImpersonationInterface;

abstract class AbstractAuthorizationPoint implements AuthorizationPointInterface
{
    
    private $workbench = null;
    
    private $app = null;
    
    private $alias = null;
    
    private $policies = null;
    
    private $active = true;
    
    private $combinationAlgorithm = null;
    
    private $defaultEffect = null;
    
    private $name = null;
    
    private $uid = null;
    
    private $isLoadedForUser = null;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     */
    public function __construct(AppInterface $app, string $alias)
    {
        $this->workbench = $app->getWorkbench();
        $this->alias = $alias;
        $this->app = $app;
        $this->workbench->model()->getModelLoader()->loadAuthorizationPoint($this);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * @return AuthorizationPolicyInterface[]
     */
    public function getPolicies(UserImpersonationInterface $userOrToken) : array
    {
        if ($this->isPolicyModelLoaded($userOrToken) === false) {
            $this->loadPolicies($userOrToken);
        }
        
        return $this->policies;
    }
    
    protected function addPolicyInstance(AuthorizationPolicyInterface $policy) : AbstractAuthorizationPoint
    {
        $this->policies[] = $policy;
        return $this;
    }
    
    public function getNamespace()
    {
        return $this->getApp()->getAliasWithNamespace();
    }
    
    public function getAlias()
    {
        return $this->alias;
    }
    
    public function getAliasWithNamespace()
    {
        return $this->getNamespace() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . $this->getAlias();
    }
    
    public function setActive(bool $trueOrFalse): AuthorizationPointInterface
    {
        $this->active = $trueOrFalse;
        return $this;
    }
    
    protected function isActive(): bool
    {
        return $this->active;
    }
    
    public function setPolicyCombiningAlgorithm(PolicyCombiningAlgorithmDataType $algorithm): AuthorizationPointInterface
    {
        $this->combinationAlgorithm = $algorithm;
        return $this;
    }
    
    public function getPolicyCombiningAlgorithm() : PolicyCombiningAlgorithmDataType
    {
        return $this->combinationAlgorithm;
    }
    
    public function setDefaultPolicyEffect(PolicyEffectDataType $effect): AuthorizationPointInterface
    {
        $this->defaultEffect = $effect;
        return $this;
    }
    
    public function getDefaultPolicyEffect() : PolicyEffectDataType
    {
        return $this->defaultEffect;
    }
    
    public function getApp(): AppInterface
    {
        return $this->app;
    }
    
    /**
     *
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }
    
    /**
     * 
     * @param string $value
     * @return AuthorizationPointInterface
     */
    public function setName(string $value) : AuthorizationPointInterface
    {
        $this->name = $value;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::getUid()
     */
    public function getUid() : string
    {
        return $this->uid;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthorizationPointInterface::setUid()
     */
    public function setUid(string $value) : AuthorizationPointInterface
    {
        $this->uid = $value;
        return $this;
    }
    
    /**
     * 
     * @param UserImpersonationInterface $userOrToken
     * @return bool
     */
    protected function isPolicyModelLoaded(UserImpersonationInterface $userOrToken) : bool
    {
        return $this->isLoadedForUser !== null && $userOrToken->getUsername() === $this->isLoadedForUser->getUsername();
    }
    
    /**
     * 
     * @param UserImpersonationInterface $userOrToken
     * @return self
     */
    protected function loadPolicies(UserImpersonationInterface $userOrToken) : self
    {
        $this->policies = [];
        $this->isLoadedForUser = null;
        $this->workbench->model()->getModelLoader()->loadAuthorizationPolicies($this, $userOrToken);
        $this->isLoadedForUser = $userOrToken;
        return $this;
    }
}