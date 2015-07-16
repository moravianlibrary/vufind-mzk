<?php
/**
 * Shibboleth authentication module crafted to manage user's connected identities using "eppn" column in user_card table.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace CPK\Auth;

use VuFind\Exception\Auth as AuthException, CPK\Db\Table\User as UserTable, CPK\Db\Row\User as UserRow, VuFind\Auth\Shibboleth as Shibboleth;
use VuFind\Exception\VuFind\Exception;

/**
 * Shibboleth authentication module.
 *
 * @category VuFind2
 * @package Authentication
 * @author Jiří Kozlovský <mail@jkozlovsky.cz>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://www.vufind.org Main Page
 */
class ShibbolethIdentityManager extends Shibboleth
{

    /**
     * This is configuration filename without ini ext to use for configuration of all the IdPs we support.
     *
     * It is actually not only used in loading this config, but also in outputting the right filename on
     * AuthException when is config validation about to be done.
     *
     * @var const CONFIG_FILE_NAME
     */
    const CONFIG_FILE_NAME = "shibboleth";

    /**
     * This is TAG used to retrieve token from user's cookie while connecting with another account.
     *
     * @var CONSOLIDATION_TOKEN_TAG
     */
    const CONSOLIDATION_TOKEN_TAG = "__connAcc";

    /**
     * This is key in $_SERVER how it Shibooleth SP returns for entityID of IdP user used to log in.
     *
     * @var const SHIB_IDENTITY_PROVIDER_ENV
     */
    const SHIB_IDENTITY_PROVIDER_ENV = 'Shib-Identity-Provider';

    /**
     * This is key in $_SERVER how it Shibooleth SP returns for Shibboleth assertion count used to
     * determine count of assertions Shibboleth SP used to be able of theoretically endless iteration
     * over those assertions.
     *
     * @var const SHIB_ASSERTION_COUNT_ENV
     */
    const SHIB_ASSERTION_COUNT_ENV = 'Shib-Assertion-Count';

    /**
     * This value stores configuration for showing assertions passed by Shibboleth SP.
     *
     * @var boolean shibAssertionExportEnabled
     */
    protected $shibAssertionExportEnabled = false;

    /**
     * It's value must match the separator MultiBackend driver uses to explode() cat_username.
     *
     * @var const SEPARATOR
     */
    const SEPARATOR = ".";

    /**
     * It's value is same as $this::SEPARATOR, but regex ready.
     *
     * @var const SEPARATOR_REGEXED
     */
    const SEPARATOR_REGEXED = "\\.";

    /**
     * This is a standalone file with filename shibboleth.ini in localconfig/config/vufind directory
     *
     * @var \Zend\Config\Config shibbolethConfig
     */
    protected $shibbolethConfig = null;

    /**
     * Holds User tableGateway to store and retrieve data from there.
     *
     * @var UserTable $userTableGateway
     */
    protected $userTableGateway = null;

    /**
     * This is array of attributes which $this->authenticate() method should check for.
     *
     * WARNING: can contain only such attributes, which are writeable to user table!
     *
     * @var array attribsToCheck
     */
    protected $attribsToCheck = array(
        'username',
        'cat_username',
        'email',
        'lastname',
        'firstname',
        'college',
        'major',
        'home_library'
    );

    public function __construct(\VuFind\Config\PluginManager $configLoader, UserTable $userTableGateway)
    {
        $this->shibbolethConfig = $configLoader->get($this::CONFIG_FILE_NAME);

        if (empty($this->shibbolethConfig)) {
            throw new AuthException("Could not load " . $this::CONFIG_FILE_NAME . ".ini configuration file.");
        }

        $this->userTableGateway = $userTableGateway;
    }

    /**
     * Validate configuration parameters.
     * This is a support method for getConfig(),
     * so the configuration MUST be accessed using $this->config; do not call
     * $this->getConfig() from within this method!
     *
     * @throws AuthException
     * @return void
     */
    protected function validateConfig()
    {
        // Throw an exception if the required login setting is missing.
        $shib = $this->config->Shibboleth;

        if (! isset($shib->login)) {
            throw new AuthException('Shibboleth login configuration parameter is not set.');
        } elseif (isset($shib->getAssertion) && $shib->getAssertion == true) {
            $this->shibAssertionExportEnabled = true;
        }

        foreach ($this->shibbolethConfig as $name => $configuration) {
            if (! isset($configuration['username']) || empty($configuration['username'])) {
                throw new AuthException("Shibboleth 'username' is missing in your " . $this::CONFIG_FILE_NAME . ".ini configuration file for '" . $name . "'");
            }

            if ($name !== 'default') {
                if (! isset($configuration['entityId']) || empty($configuration['entityId'])) {
                    throw new AuthException("Shibboleth 'entityId' is missing in your " . $this::CONFIG_FILE_NAME . ".ini configuration file for '" . $name . "'");
                } elseif (! isset($configuration['cat_username']) || empty($configuration['cat_username'])) {
                    throw new AuthException("Shibboleth 'cat_username' is missing in your " . $this::CONFIG_FILE_NAME . ".ini configuration file for '" . $name . "' with entityId " . $configuration['entityId']);
                }
            }
        }
    }

    /**
     * Perform cleanup at logout time.
     *
     * @param string $url
     *            URL to redirect user to after logging out.
     *
     * @return string Redirect URL (usually same as $url, but modified in
     *         some authentication modules).
     */
    public function logout($url)
    {
        // If single log-out is enabled, use a special URL:
        $logoutEndpoint = $this->config->Shibboleth->logout;

        if (isset($logoutEndpoint) && ! empty($logoutEndpoint)) {
            $url = $logoutEndpoint . '?return=' . urlencode($url);
        }

        // Send back the redirect URL (possibly modified):
        return $url;
    }

    public function getAccountConsolidationRedirect() {
        // Local logout can be only identity connection for now, thus we need to handle the consolidation token
        $this->handleConsolidationToken($_SERVER['eduPersonPrincipalName']);
        return $this->createLocalLogoutURL();
    }

    public function authenticate($request = null, UserRow $userToConnectWith = null)
    {
        // We don't need it - is this better than unset?
        $request = null;

        $entityId = $_SERVER[self::SHIB_IDENTITY_PROVIDER_ENV];
        $config = null;
        $prefix = null;

        $loggedWithKnownEntityId = false;
        foreach ($this->shibbolethConfig as $name => $configuration) {
            if ($entityId == $configuration['entityId']) {
                $config = $configuration;
                $prefix = $name;
                $loggedWithKnownEntityId = true;
                break;
            }
        }

        if ($config == null) {
            if (isset($this->shibbolethConfig['default'])) {
                $config = $this->shibbolethConfig['default'];
                $prefix = 'default';
            } else
                throw new AuthException('Recieved entityId was not found in " . $this::CONFIG_FILE_NAME . ".ini config nor default config part exists.');
        }

        $attributes = $this->fetchAttributes($config);

        if (empty($attributes['username'])) {
            throw new AuthException('IdP "' . $prefix . '" didn\'t provide mandatory attribute: "' . $configuration['username'] . '"');
        } else
            $eppn = $attributes['username'];

            // Home Library should be the prefix from shibboleth.ini config by default
        if (empty($attributes['home_library'])) {
            $attributes['home_library'] = $prefix;
        }

        // Get UserRow by checking for known eppn
        $user = $this->userTableGateway->getByEppn($eppn);

        if ($user)
            $libCards = $user->getLibraryCards(false);

            // Now we need to know if there is a request to connect two identities
        $connectIdentities = $userToConnectWith !== null;
        if ($connectIdentities) {
            $currentUser = $user;

            if ($loggedWithKnownEntityId) {
                // If user logged in with known entityID, we need userLibraryId to save into cat_username
                if (empty($attributes['cat_username'])) {
                    throw new AuthException('IdP "' . $prefix . '" didn\'t provide mandatory attribute: "' . $configuration['cat_username'] . '"');
                }

                // Set here the prefix to let MultiBackend understand which Driver it needs
                $attributes['cat_username'] = $prefix . self::SEPARATOR . $attributes['cat_username'];
            } else {
                // We now detected unkown entityID

                // Set cat_username to last cat_username because we always update user table using $attributes variable
                $attributes['cat_username'] = $userToConnectWith->cat_username;
                $attributes['home_library'] = $userToConnectWith->home_library;
            }

            if ($currentUser === false) {
                // Replace user table with current fetched attributes & append new library card
                if ($loggedWithKnownEntityId) {
                    $this->createLibraryCard($userToConnectWith, $attributes['cat_username'], $attributes['home_library'], $eppn);
                } else {
                    $this->createLibraryCard($userToConnectWith, "Dummy.Dummy", "Dummy", $eppn);
                }
            } else {
                // We will always keep the one account created as the first
                $switchRoles = $currentUser->id < $userToConnectWith->id;

                if ($switchRoles) {
                    $tmp = $currentUser;
                    $currentUser = $userToConnectWith;
                    $userToConnectWith = $tmp;
                    unset($tmp);
                }

                $this->transferLibraryCards($currentUser, $userToConnectWith);

                // We need to check, if there doesn't exist library card with the same institution to update it
                $this->updateIdentityCatUsername($userToConnectWith, $prefix, $attributes['cat_username']);

                // TODO: Merge favorites
                // TODO: Call $currentUser->delete() then
            }

            if ($attributes['email'] == null)
                $attributes['email'] = '';
            if ($attributes['firstname'] == null)
                $attributes['firstname'] = '';
            if ($attributes['lastname'] == null)
                $attributes['lastname'] = '';

            foreach ($attributes as $key => $value) {
                $userToConnectWith->$key = $value;
            }

            // Update user in database
            $userToConnectWith->save();

            return $userToConnectWith;
        } else {
            // If there was no User found, create one
            if (! $user) {

                // eppn will be user's username
                $user = $this->userTableGateway->createRowForUsername($eppn);

                $userWasCreatedNow = true;
            } else
                $userWasCreatedNow = false;

            $skipUserSave = false;
            if ($loggedWithKnownEntityId) {

                // If user logged in with known entityID, we need userLibraryId to save into cat_username
                if (empty($attributes['cat_username'])) {
                    throw new AuthException('IdP "' . $prefix . '" didn\'t provide mandatory attribute: "' . $configuration['cat_username'] . '"');
                }

                // Set here the prefix to let MultiBackend understand which Driver it needs
                $attributes['cat_username'] = $prefix . self::SEPARATOR . $attributes['cat_username'];

                // We need to check, if there doesn't exist library card with the same institution to update it
                if (! $userWasCreatedNow) {
                    // We didn't create the user recently so we already know rowId, thus we can update libCards right now
                    $this->updateIdentityCatUsername($user, $prefix, $attributes['cat_username']);
                }
            } else {
                // We now detected unkown entityID

                // There is a possibility of user being have connected active library
                if (! $userWasCreatedNow) {
                    // Set cat_username to last cat_username because we always update user table using $attributes variable
                    $skipUserSave = true;
                } else {
                    $attributes['cat_username'] = 'Dummy.Dummy';
                    $attributes['home_library'] = 'Dummy';
                }
            }

            if (! $skipUserSave) {
                if ($attributes['email'] == null)
                    $attributes['email'] = '';
                if ($attributes['firstname'] == null)
                    $attributes['firstname'] = '';
                if ($attributes['lastname'] == null)
                    $attributes['lastname'] = '';

                foreach ($attributes as $key => $value) {
                    $user->$key = $value;
                }

                // Save/Update user in database
                $user->save();

                if ($userWasCreatedNow) {
                    // we also need to assign the user new library card if we have created him recently
                    $this->createLibraryCard($user, $attributes['cat_username'], $attributes['home_library'], $eppn);
                }
            }

            return $user;
        }
    }

    public function connectIdentity($entityIdInitiatedWith)
    {
        $currentEntityId = $_SERVER[self::SHIB_IDENTITY_PROVIDER_ENV];

        if ($currentEntityId === $entityIdInitiatedWith)
            throw new AuthException("Cannot connect two accounts from the same institution. Please try again.");

            // Just authenticate for now ...
        $token = $_COOKIE[$this::CONSOLIDATION_TOKEN_TAG];

        if (empty($token))
            throw new AuthException("No token recieved after logging with another account. Please try it again.");

        $userToConnectWith = $this->userTableGateway->getUserFromConsolidationToken($token);

        if (! $userToConnectWith)
            throw new AuthException("It was supplied an invalid token to connect another account. Please try the whole process again.");

        return $this->authenticate(null, $userToConnectWith);
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).
     * Returns false when no session initiator is needed.
     *
     * @param string $target
     *            Full URL where external authentication method should
     *            send user to after login (some drivers may override this).
     *
     * @return array
     */
    public function getSessionInitiators($target)
    {
        $this->init();
        $config = $this->getConfig();
        if (isset($config->Shibboleth->target)) {
            $shibTarget = $config->Shibboleth->target;
        } else {
            $shibTarget = $target;
        }
        $initiators = array();
        foreach ($this->shibbolethConfig as $name => $configuration) {
            $entityId = $configuration['entityId'];
            $loginUrl = $config->Shibboleth->login . '?target=' . urlencode($shibTarget) . '&entityID=' . urlencode($entityId);
            $initiators[$name] = $loginUrl;
        }
        return $initiators;
    }

    /**
     * Checks for User's cat_username in his libCards to see, if provided $cat_username
     * matches the libCard's cat_username unless the prefix of cat_username doesn't match.
     *
     * If the provided $cat_username differs from libCard's cat_username, it is than updated.
     *
     * @param User $user
     * @param string $prefix
     * @param string $cat_username
     */
    protected function updateIdentityCatUsername(UserRow $user, $prefix, $cat_username)
    {
        // We do not need dummy cards .. pass false here
        $resultSet = $user->getLibraryCards(false);

        foreach ($resultSet as $libraryCard) {
            $libCard_cat_username = $libraryCard->cat_username;
            $libCard_prefix = split($this::SEPARATOR_REGEXED, $libCard_cat_username)[0];

            // We are performing the check of corresponding institutions by comparing the prefix
            // from shibboleth.ini config section name with MultiBackend's source from cat_username
            if ($libCard_prefix === $prefix) {
                // now check if the cat_username matches ..
                if ($libCard_cat_username !== $cat_username) {

                    // else update it
                    $libraryCard->cat_username = $cat_username;
                    $libraryCard->save();
                }

                // There is always only one match, thus we can break the cycle
                break;
            }
        }
    }

    /**
     * Creates library card for User $user with $cat_username & $home_library identified by $eppn.
     *
     * eduPersonPrincipalName is later used to identify loggedin user.
     *
     * Returns library card id on success. Otherwise returns false.
     *
     * @param UserRow $user
     * @param string $cat_username
     * @param string $home_library
     * @param string $eppn
     * @return mixed int | boolean
     */
    protected function createLibraryCard(UserRow $user, $cat_username, $home_library, $eppn)
    {
        try {
            if (empty($eppn))
                throw new \VuFind\Exception\LibraryCard("Cannot create library card with empty eppn");

            return $user->saveLibraryCard(null, '', $cat_username, null, $home_library, $eppn);
        } catch (\VuFind\Exception\LibraryCard $e) { // If an exception is thrown, just show a flash message ..
            $exceptions = $_ENV['exception'];

            if ($exceptions == null) {
                $_ENV['exception'] = $e->getMessage();
            } else {
                $_ENV['exception'] .= "\n" . $e->getMessage();
            }

            return false;
        }
    }

    protected function transferLibraryCards(UserRow $from, UserRow $into)
    {
        // We need all user's cards here ... pass true
        $libCards = $from->getLibraryCards(true);
        foreach ($libCards as $libCard) {
            // First delete the old one to always have unique eppn accross the user_card table
            $from->deleteLibraryCard($libCard->id);

            $this->createLibraryCard($into, $libCard->cat_username, $libCard->home_library, $libCard->eppn);
        }
    }

    /**
     * Maps premapped attributes from shibboleth.ini particular section where is know-how for parsing
     * attributes the IdP returned.
     *
     * It basically returns array $attributes, which is later saved to 'user' table as current user.
     * There may be some minor modifications, e.g. to cat_username is appended institute delimited
     * by $this::SEPARATOR.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request
     * @param \Zend\Config\Config $config
     *            containing only array of attributes mapping from attribute-map.xml to user table in VuFind
     * @return array attributes
     */
    protected function fetchAttributes($config)
    {
        $attributes = array();
        foreach ($this->attribsToCheck as $attribute) {
            if (isset($config->$attribute)) {
                $key = $config->$attribute;
                $pattern = null;
                $value = null;
                if (strpos($key, '|') !== false) {
                    $keys = explode('|', $key);
                    foreach ($keys as $key) {
                        $key = trim($key);
                        $value = $_SERVER[$key];
                        if ($value != null) {
                            break;
                        }
                    }
                } elseif (strpos($key, ',') !== false) {
                    list ($key, $pattern) = explode(',', $key, 2);
                    $pattern = trim($pattern);
                }
                if ($value == null) {
                    $value = $_SERVER[$key];
                }
                if ($pattern != null) {
                    $matches = array();
                    preg_match($pattern, $value, $matches);
                    $value = $matches[1];
                }

                $attributes[$attribute] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Returns true if the assertion export is enabled in config.ini [Shibboleth] section.
     *
     * You can enable it by typing "getAssertion = 1" to [Shibboleth] config section. Note that
     * it has to be enabled in apache configuration too.
     *
     * @return boolean shibAssertionExportEnabled
     */
    public function isShibAssertionExportEnabled()
    {
        return $this->shibAssertionExportEnabled;
    }

    /**
     * This function returns array of assertions Shibboleth SP sent us.
     * If the PHP script was unable to
     * load contents of provided links, then each array element contains the link to parse the assertion.
     *
     * @return array assertions
     */
    public function getShibAssertions()
    {
        $assertions = array();

        $count = intval($_SERVER[$this::SHIB_ASSERTION_COUNT_ENV]);

        if (! empty($count))
            for ($i = 0; $i < $count; ++ $i) {
                $shibAssertionEnv = $this->getShibAssertionNumberEnv($i + 1);

                $assertions[$i] = $_SERVER[$shibAssertionEnv];

                if ($assertions[$i] == null) {
                    unset($assertions[$i]);
                } else {
                    $contents = file_get_contents($assertions[$i]);

                    // If we have parsed contents successfully, set it to assertion
                    // If not, then leave there the link to find out what is the problem
                    if (! empty($contents)) {
                        $assertions[$i] = $contents;
                    }
                }
            }

        return $assertions;
    }

    protected function getShibAssertionNumberEnv($i)
    {
        if ($i < 10) {
            return 'Shib-Assertion-0' . $i;
        } else {
            return 'Shib-Assertion-' . $i;
        }
    }

    protected function createLocalLogoutURL()
    {
        $hostname = $this->config->Site->url;

        if (substr($hostname, - 1) === '/') {
            $hostname = substr($hostname, 0, - 1);
        }

        $target = $hostname . '/MyResearch/UserConnect';

        $entityId = $_SERVER[self::SHIB_IDENTITY_PROVIDER_ENV];
        $target .= '?eid=' . urlencode($entityId);

        return $this->config->Shibboleth->login . '?target=' . $target;
    }

    /**
     * Handles generating & saving token to verify User's identity after connection
     * from another identity to merge those identities.
     * It also saves this token
     * to session table where "data" column has value of user's row id from user table.
     *
     * @param string $eppn
     */
    protected function handleConsolidationToken($eppn)
    {
        $token = $this->generateToken();
        setCookie($this::CONSOLIDATION_TOKEN_TAG, $token);

        $this->userTableGateway->saveUserConsolidationToken($token, $eppn);
    }

    /**
     * Returns 32-length string token.
     *
     * @return string Token
     */
    protected function generateToken()
    {
        return chr(mt_rand(97, 122)) . chr(mt_rand(97, 122)) . substr(md5(time()), 3) . chr(mt_rand(97, 122));
    }
}