<?php
namespace exface\Core\Interfaces;

use exface\Core\CommonLogic\Model\User;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Selectors\UserRoleSelectorInterface;

interface UserInterface extends UserImpersonationInterface, WorkbenchDependantInterface
{

    /**
     * Returns the UID of the user.
     * 
     * @return string
     */
    public function getUid();

    /**
     * Sets the username of the user.
     * 
     * @param string $username
     * @return User
     */
    public function setUsername($username);
    
    /**
     * 
     * @return string|NULL
     */
    public function getPassword() : ?string;
    
    /**
     * 
     * @param string $value
     * @return UserInterface
     */
    public function setPassword(string $value) : UserInterface;

    /**
     * Returns the first name of the user.
     * 
     * @return string
     */
    public function getFirstName();

    /**
     * Sets the first name of the user.
     * 
     * @param string $firstname
     * @return User
     */
    public function setFirstName($firstname);

    /**
     * Returns the last name of the user.
     * 
     * @return string
     */
    public function getLastName();

    /**
     * Sets the last name of the user.
     * 
     * @param string $lastname
     * @return User
     */
    public function setLastName($lastname);

    /**
     * Returns the locale of the user (e.g. 'en_US').
     * 
     * @return string
     */
    public function getLocale();

    /**
     * Sets the locale of the user.
     * 
     * @param string $locale
     * @return User
     */
    public function setLocale($locale);

    /**
     * Returns the email of the user.
     * 
     * @return string
     */
    public function getEmail();

    /**
     * Sets the email of the user.
     * 
     * @param string $email
     * @return User
     */
    public function setEmail($email);

    /**
     * Returns a DataSheet representing all properties of the user.
     * 
     * @return DataSheetInterface
     */
    public function exportDataSheet();
    
    /**
     * 
     * @param UserInterface $otherUser
     * @return bool
     */
    public function is(UserInterface $otherUser) : bool;
    
    /**
     * Returns TRUE if the user has a model and, thus, may have a credential storage, etc.
     * 
     * @return bool
     */
    public function hasModel() : bool;
    
    /**
     * 
     * @return string
     */
    public function getInitials() : string;
    
    /**
     * 
     * @param UserRoleSelectorInterface $selector
     * @return bool
     */
    public function hasRole(UserRoleSelectorInterface $selector) : bool;
}