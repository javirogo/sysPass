<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2017, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Controller;

defined('APP_ROOT') || die();

use SP\Account\Account;
use SP\Account\AccountAcl;
use SP\Account\AccountHistory;
use SP\Account\AccountUtil;
use SP\Account\UserAccounts;
use SP\Core\Acl\ActionsInterface;
use SP\Core\Crypt\Crypt;
use SP\Core\Exceptions\SPException;
use SP\Core\Init;
use SP\Core\SessionFactory;
use SP\Core\SessionUtil;
use SP\DataModel\AccountExtData;
use SP\DataModel\CustomFieldData;
use SP\DataModel\PublicLinkData;
use SP\Mgmt\Categories\Category;
use SP\Mgmt\Customers\Customer;
use SP\Mgmt\CustomFields\CustomField;
use SP\Mgmt\Groups\Group;
use SP\Mgmt\Groups\GroupAccountsUtil;
use SP\Mgmt\PublicLinks\PublicLink;
use SP\Mgmt\Tags\Tag;
use SP\Mgmt\Users\UserPass;
use SP\Mgmt\Users\UserUtil;
use SP\Modules\Web\Controllers\ControllerBase;
use SP\Mvc\View\Template;
use SP\Util\ImageUtil;
use SP\Util\Json;

/**
 * Clase encargada de preparar la presentación de las vistas de una cuenta
 *
 * @package Controller
 */
class AccountController extends ControllerBase implements ActionsInterface
{
    /**
     * @var AccountAcl
     */
    protected $AccountAcl;
    /**
     * @var Account|AccountHistory instancia para el manejo de datos de una cuenta
     */
    private $Account;
    /**
     * @var int con el id de la cuenta
     */
    private $id;
    /**
     * @var AccountExtData
     */
    private $AccountData;

    /**
     * Constructor
     *
     * @param \SP\Mvc\View\Template $template  instancia del motor de plantillas
     * @param int                   $accountId int con el id de la cuenta
     * @internal param int $lastAction int con la última acción realizada
     */
    public function __construct(Template $template = null, $accountId = null)
    {
        parent::__construct($template);

        $this->setId($accountId);

        $this->view->assign('changesHash');
        $this->view->assign('chkUserEdit');
        $this->view->assign('chkGroupEdit');
        $this->view->assign('gotData', $this->isGotData());
        $this->view->assign('isView', false);
        $this->view->assign('sk', SessionUtil::getSessionKey(true));
    }

    /**
     * @param int $id
     */
    private function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return boolean
     */
    private function isGotData()
    {
        return $this->AccountData !== null;
    }

    /**
     * Obtener la vista de detalles de cuenta para enlaces públicos
     *
     * @param PublicLinkData $PublicLinkData
     *
     */
    public function getAccountFromLink(PublicLinkData $PublicLinkData)
    {
        $this->setAction(self::ACCOUNT_VIEW);

        $this->view->addTemplate('account-link');
        $this->view->assign('title',
            [
                'class' => 'titleNormal',
                'name' => __('Detalles de Cuenta'),
                'icon' => $this->icons->getIconView()->getIcon()
            ]
        );

        try {
            $Account = new Account();
            $Account->incrementViewCounter($PublicLinkData->getItemId());
            $Account->incrementDecryptCounter($PublicLinkData->getItemId());

            $key = $this->configData->getPasswordSalt() . $PublicLinkData->getPublicLinkLinkHash();
            $securedKey = Crypt::unlockSecuredKey($PublicLinkData->getPassIV(), $key);

            /** @var AccountExtData $AccountData */
            $AccountData = unserialize(Crypt::decrypt($PublicLinkData->getData(), $securedKey, $key));

            $this->view->assign('useImage', $this->configData->isPublinksImageEnabled() || $this->configData->isAccountPassToImage());

            $accountPass = $this->view->useImage ? ImageUtil::convertText($AccountData->getPass()) : $AccountData->getPass();

            $this->view->assign('accountPass', $accountPass);
            $this->view->assign('accountData', $AccountData);
        } catch (\Exception $e) {
            $this->showError(self::ERR_EXCEPTION);
        }
    }

    /**
     * Realizar las acciones del controlador
     *
     * @param mixed $type Tipo de acción
     */
    public function doAction($type = null)
    {
        try {
            switch ($type) {
                case ActionsInterface::ACCOUNT_CREATE:
                    $this->getNewAccount();
                    $this->eventDispatcher->notifyEvent('show.account.new', $this);
                    break;
                case ActionsInterface::ACCOUNT_COPY:
                    $this->getCopyAccount();
                    $this->eventDispatcher->notifyEvent('show.account.copy', $this);
                    break;
                case ActionsInterface::ACCOUNT_EDIT:
                    $this->getEditAccount();
                    $this->eventDispatcher->notifyEvent('show.account.edit', $this);
                    break;
                case ActionsInterface::ACCOUNT_EDIT_PASS:
                    $this->getEditPassAccount();
                    $this->eventDispatcher->notifyEvent('show.account.editpass', $this);
                    break;
                case ActionsInterface::ACCOUNT_VIEW:
                    $this->getViewAccount();
                    $this->eventDispatcher->notifyEvent('show.account.view', $this);
                    break;
                case ActionsInterface::ACCOUNT_VIEW_HISTORY:
                    $this->getViewHistoryAccount();
                    $this->eventDispatcher->notifyEvent('show.account.viewhistory', $this);
                    break;
                case ActionsInterface::ACCOUNT_DELETE:
                    $this->getDeleteAccount();
                    $this->eventDispatcher->notifyEvent('show.account.delete', $this);
                    break;
                case ActionsInterface::ACCOUNT_REQUEST:
                    $this->getRequestAccountAccess();
                    $this->eventDispatcher->notifyEvent('show.account.request', $this);
                    break;
            }
        } catch (SPException $e) {
            $this->showError(self::ERR_EXCEPTION);
        }
    }

    /**
     * Obtener los datos para mostrar el interface para nueva cuenta
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getNewAccount()
    {
        $this->setAction(self::ACCOUNT_CREATE);

        if (!$this->checkAccess()) {
            return;
        }

        $this->view->addTemplate('account');
        $this->view->assign('title',
            [
                'class' => 'titleGreen',
                'name' => __('Nueva Cuenta'),
                'icon' => $this->icons->getIconAdd()->getIcon()
            ]
        );

        SessionFactory::setLastAcountId(0);
        $this->setCommonData();
    }

    /**
     * Comprobar si el usuario dispone de acceso al módulo
     *
     * @param null $action
     * @return bool
     */
    protected function checkAccess($action = null)
    {
        $this->view->assign('showLogo', false);

        $Acl = new AccountAcl($this->getAction());
        $this->AccountAcl = $Acl;

        if (!$this->acl->checkUserAccess($this->getAction())) {
            $this->showError(self::ERR_PAGE_NO_PERMISSION);
            return false;
        }

        if (!UserPass::checkUserUpdateMPass($this->userData->getId())) {
            $this->showError(self::ERR_UPDATE_MPASS);
            return false;
        }

        if ($this->id > 0) {
            $this->AccountAcl = $Acl->getAcl();

            if (!$this->AccountAcl->checkAccountAccess()) {
                $this->showError(self::ERR_ACCOUNT_NO_PERMISSION);
                return false;
            }

            SessionFactory::setAccountAcl($this->AccountAcl);
        }

        return true;
    }

    /**
     * Establecer variables comunes del formulario para todos los interfaces
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    private function setCommonData()
    {
        $this->getCustomFieldsForItem();

        if ($this->isGotData()) {
            $this->view->assign('accountIsHistory', $this->getAccount()->getAccountIsHistory());
            $this->view->assign('accountOtherUsers', UserAccounts::getUsersInfoForAccount($this->getId()));
            $this->view->assign('accountOtherGroups', GroupAccountsUtil::getGroupsInfoForAccount($this->getId()));
            $this->view->assign('accountTagsJson', Json::getJson(array_keys($this->getAccount()->getAccountData()->getTags())));
            $this->view->assign('historyData', AccountHistory::getAccountList($this->AccountData->getId()));
            $this->view->assign('isModified', strtotime($this->AccountData->getDateEdit()) !== false);
            $this->view->assign('maxFileSize', round($this->configData->getFilesAllowedSize() / 1024, 1));
            $this->view->assign('filesAllowedExts', implode(',', $this->configData->getFilesAllowedExts()));

            $PublicLinkData = PublicLink::getItem()->getHashForItem($this->getId());

            $publicLinkUrl = ($this->configData->isPublinksEnabled() && $PublicLinkData ? Init::$WEBURI . '/index.php?h=' . $PublicLinkData->getHash() . '&a=link' : null);
            $this->view->assign('publicLinkUrl', $publicLinkUrl);
            $this->view->assign('publicLinkId', $PublicLinkData ? $PublicLinkData->getId() : 0);

            $this->view->assign('accountPassDate', date('Y-m-d H:i:s', $this->AccountData->getPassDate()));
            $this->view->assign('accountPassDateChange', date('Y-m-d', $this->AccountData->getPassDateChange() ?: 0));
        } else {
            $this->view->assign('accountPassDateChange', date('Y-m-d', time() + 7776000));
        }

        $this->view->assign('actionId', $this->getAction());
        $this->view->assign('categories', Category::getItem()->getItemsForSelect());
        $this->view->assign('customers', Customer::getItem()->getItemsForSelectByUser());
        $this->view->assign('otherUsers', UserUtil::getUsersLogin());
        $this->view->assign('otherUsersJson', Json::getJson($this->view->otherUsers));
        $this->view->assign('otherGroups', Group::getItem()->getItemsForSelect());
        $this->view->assign('otherGroupsJson', Json::getJson($this->view->otherGroups));
        $this->view->assign('tagsJson', Json::getJson(Tag::getItem()->getItemsForSelect()));
        $this->view->assign('allowPrivate', $this->userProfileData->isAccPrivate());
        $this->view->assign('allowPrivateGroup', $this->userProfileData->isAccPrivateGroup());
        $this->view->assign('mailRequestEnabled', $this->configData->isMailRequestsEnabled());
        $this->view->assign('passToImageEnabled', $this->configData->isAccountPassToImage());

        $this->view->assign('otherAccounts', AccountUtil::getAccountsForUser($this->getId()));
        $this->view->assign('linkedAccounts', AccountUtil::getLinkedAccounts($this->getId()));

        $this->view->assign('disabled', $this->view->isView ? 'disabled' : '');
        $this->view->assign('readonly', $this->view->isView ? 'readonly' : '');

        $this->view->assign('showViewCustomPass', $this->AccountAcl->isShowViewPass());
        $this->view->assign('AccountAcl', $this->AccountAcl);
    }

    /**
     * Obtener la lista de campos personalizados y sus valores
     */
    private function getCustomFieldsForItem()
    {
        $this->view->assign('customFields', CustomField::getItem(new CustomFieldData(ActionsInterface::ACCOUNT))->getById($this->getId()));
    }

    /**
     * @return int
     */
    private function getId()
    {
        return $this->id;
    }

    /**
     * @return \SP\Account\Account|AccountHistory
     */
    private function getAccount()
    {
        return $this->Account ?: new Account(new AccountExtData());
    }

    /**
     * Obtener los datos para mostrar el interface para copiar cuenta
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getCopyAccount()
    {
        $this->setAction(self::ACCOUNT_COPY);

        // Obtener los datos de la cuenta antes y comprobar el acceso
        $isOk = ($this->setAccountData() && $this->checkAccess());

        if (!$isOk) {
            return;
        }

        $this->view->addTemplate('account');
        $this->view->assign('title',
            [
                'class' => 'titleGreen',
                'name' => __('Copiar Cuenta'),
                'icon' => $this->icons->getIconCopy()->getIcon()
            ]
        );

        $this->setCommonData();
    }

    /**
     * Establecer las variables que contienen la información de la cuenta.
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    private function setAccountData()
    {
        $Account = new Account(new AccountExtData($this->getId()));
        $this->Account = $Account;
        $this->AccountData = $Account->getData();

        $this->view->assign('accountId', $this->getId());
        $this->view->assign('accountData', $this->AccountData);
        $this->view->assign('gotData', $this->isGotData());

        return true;
    }

    /**
     * Obtener los datos para mostrar el interface para editar cuenta
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getEditAccount()
    {
        $this->setAction(self::ACCOUNT_EDIT);

        // Obtener los datos de la cuenta antes y comprobar el acceso
        $isOk = ($this->setAccountData() && $this->checkAccess());

        if (!$isOk) {
            return;
        }

        $this->view->addTemplate('account');
        $this->view->assign('title',
            [
                'class' => 'titleOrange',
                'name' => __('Editar Cuenta'),
                'icon' => $this->icons->getIconEdit()->getIcon()
            ]
        );

        $this->setCommonData();
    }

    /**
     * Obtener los datos para mostrar el interface para modificar la clave de cuenta
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getEditPassAccount()
    {
        $this->setAction(self::ACCOUNT_EDIT_PASS);

        // Obtener los datos de la cuenta antes y comprobar el acceso
        $isOk = ($this->setAccountData() && $this->checkAccess());

        if (!$isOk) {
            return;
        }

        $this->view->addTemplate('account-editpass');
        $this->view->assign('title',
            [
                'class' => 'titleOrange',
                'name' => __('Modificar Clave de Cuenta'),
                'icon' => $this->icons->getIconEditPass()->getIcon()
            ]
        );

        $this->view->assign('accountPassDateChange', gmdate('Y-m-d', $this->AccountData->getPassDateChange()));
    }

    /**
     * Obtener los datos para mostrar el interface para ver cuenta
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getViewAccount()
    {
        $this->setAction(self::ACCOUNT_VIEW);

        // Obtener los datos de la cuenta antes y comprobar el acceso
        $isOk = ($this->setAccountData() && $this->checkAccess());

        if (!$isOk) {
            return;
        }

        $this->view->addTemplate('account');
        $this->view->assign('title',
            [
                'class' => 'titleNormal',
                'name' => __('Detalles de Cuenta'),
                'icon' => $this->icons->getIconView()->getIcon()
            ]
        );

        $this->view->assign('isView', true);

        $this->Account->incrementViewCounter();

        $this->setCommonData();
    }

    /**
     * Obtener los datos para mostrar el interface para ver cuenta en fecha concreta
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getViewHistoryAccount()
    {
        $this->setAction(self::ACCOUNT_VIEW_HISTORY);

        // Obtener los datos de la cuenta antes y comprobar el acceso
        $isOk = ($this->setAccountDataHistory() && $this->checkAccess());

        if (!$isOk) {
            return;
        }

        $this->view->addTemplate('account');
        $this->view->assign('title',
            [
                'class' => 'titleNormal',
                'name' => __('Detalles de Cuenta'),
                'icon' => 'access_time'
            ]
        );

        $this->view->assign('isView', true);
        $this->Account->setAccountIsHistory(1);

        $this->setCommonData();
    }

    /**
     * Establecer las variables que contienen la información de la cuenta en una fecha concreta.
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    private function setAccountDataHistory()
    {
        $Account = new AccountHistory(new AccountExtData());
        $Account->setId($this->getId());
        $this->Account = $Account;
        $this->AccountData = $Account->getData();

        $this->view->assign('accountId', $this->AccountData->getId());
        $this->view->assign('accountData', $this->AccountData);
        $this->view->assign('gotData', $this->isGotData());

        $this->view->assign('accountHistoryId', $this->getId());

        return true;
    }

    /**
     * Obtener los datos para mostrar el interface de eliminar cuenta
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getDeleteAccount()
    {
        $this->setAction(self::ACCOUNT_DELETE);

        // Obtener los datos de la cuenta antes y comprobar el acceso
        $isOk = ($this->setAccountData() && $this->checkAccess());

        if (!$isOk) {
            return;
        }

        $this->view->addTemplate('account');
        $this->view->assign('title',
            [
                'class' => 'titleRed',
                'name' => __('Eliminar Cuenta'),
                'icon' => $this->icons->getIconDelete()->getIcon()
            ]
        );

        $this->setCommonData();
    }

    /**
     * Obtener los datos para mostrar el interface de solicitud de cambios en una cuenta
     *
     * @throws \SP\Core\Exceptions\SPException
     */
    public function getRequestAccountAccess()
    {
        // Obtener los datos de la cuenta
        $this->setAccountData();

        $this->view->addTemplate('request');
    }
}