<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Forms\Security;

use InvalidArgumentException;
use LogicException;
use Icinga\Application\Icinga;
use Icinga\Forms\ConfigForm;
use Icinga\Util\String;

/**
 * Form for managing roles
 */
class RoleForm extends ConfigForm
{
    /**
     * Provided permissions by currently loaded modules
     *
     * @var array
     */
    protected $providedPermissions = array();

    /**
     * Provided restrictions by currently loaded modules
     *
     * @var array
     */
    protected $providedRestrictions = array();

    /**
     * (non-PHPDoc)
     * @see \Icinga\Web\Form::init() For the method documentation.
     */
    public function init()
    {
        foreach (Icinga::app()->getModuleManager()->getLoadedModules() as $module) {
            foreach ($module->getProvidedPermissions() as $permission) {
                /** @var object $permission */
                $this->providedPermissions[$permission->name] = $permission->name . ': ' . $permission->description;
            }
            foreach ($module->getProvidedRestrictions() as $restriction) {
                /** @var object $restriction */
                $this->providedRestrictions[$restriction->name] = $restriction->description;
            }
        }
    }

    /**
     * (non-PHPDoc)
     * @see \Icinga\Web\Form::createElements() For the method documentation.
     */
    public function createElements(array $formData = array())
    {
        $this->addElements(array(
            array(
                'text',
                'name',
                array(
                    'required'      => true,
                    'label'         => t('Role Name'),
                    'description'   => t('The name of the role'),
                    'ignore'        => true
                ),
            ),
            array(
                'textarea',
                'users',
                array(
                    'label'         => t('Users'),
                    'description'   => t('Comma-separated list of users that are assigned to the role')
                ),
            ),
            array(
                'textarea',
                'groups',
                array(
                    'label'         => t('Groups'),
                    'description'   => t('Comma-separated list of groups that are assigned to the role')
                ),
            ),
            array(
                'multiselect',
                'permissions',
                array(
                    'label'         => t('Permissions Set'),
                    'description'   => t('The permissions to grant. You may select more than one permission'),
                    'multiOptions'  => $this->providedPermissions
                )
            )
        ));
        foreach ($this->providedRestrictions as $name => $description) {
            $this->addElement(
                'text',
                $name,
                array(
                    'label'         => $name,
                    'description'   => $description
                )
            );
        }
        return $this;
    }

    /**
     * Load a role
     *
     * @param   string  $name           The name of the role
     *
     * @return  $this
     *
     * @throws  LogicException          If the config is not set
     * @see     ConfigForm::setConfig() For setting the config.
     */
    public function load($name)
    {
        if (! isset($this->config)) {
            throw new LogicException(sprintf('Can\'t load role \'%s\'. Config is not set', $name));
        }
        if (! $this->config->hasSection($name)) {
            throw new InvalidArgumentException(sprintf(
                t('Can\'t load role \'%s\'. Role does not exist'),
                $name
            ));
        }
        $role = $this->config->getSection($name)->toArray();
        $role['permissions'] = ! empty($role['permissions'])
            ? String::trimSplit($role['permissions'])
            : null;
        $role['name'] = $name;
        $this->populate($role);
        return $this;
    }

    /**
     * Add a role
     *
     * @param   string  $name               The name of the role
     * @param   array   $values
     *
     * @return  $this
     *
     * @throws  LogicException              If the config is not set
     * @throws  InvalidArgumentException    If the role to add already exists
     * @see     ConfigForm::setConfig()     For setting the config.
     */
    public function add($name, array $values)
    {
        if (! isset($this->config)) {
            throw new LogicException(sprintf('Can\'t add role \'%s\'. Config is not set', $name));
        }
        if ($this->config->hasSection($name)) {
            throw new InvalidArgumentException(sprintf(
                t('Can\'t add role \'%s\'. Role already exists'),
                $name
            ));
        }
        $this->config->setSection($name, $values);
        return $this;
    }

    /**
     * Remove a role
     *
     * @param   string  $name               The name of the role
     *
     * @return  $this
     *
     * @throws  LogicException              If the config is not set
     * @throws  InvalidArgumentException    If the role does not exist
     * @see     ConfigForm::setConfig()     For setting the config.
     */
    public function remove($name)
    {
        if (! isset($this->config)) {
            throw new LogicException(sprintf('Can\'t remove role \'%s\'. Config is not set', $name));
        }
        if (! $this->config->hasSection($name)) {
            throw new InvalidArgumentException(sprintf(
                t('Can\'t remove role \'%s\'. Role does not exist'),
                $name
            ));
        }
        $this->config->removeSection($name);
        return $this;
    }

    /**
     * Update a role
     *
     * @param   string  $name               The possibly new name of the role
     * @param   array   $values
     * @param   string  $oldName            The name of the role to update
     *
     * @return  $this
     *
     * @throws  LogicException              If the config is not set
     * @throws  InvalidArgumentException    If the role to update does not exist
     * @see     ConfigForm::setConfig()     For setting the config.
     */
    public function update($name, array $values, $oldName)
    {
        if (! isset($this->config)) {
            throw new LogicException(sprintf('Can\'t update role \'%s\'. Config is not set', $name));
        }
        if ($name !== $oldName) {
            // The permission got a new name
            $this->remove($oldName);
            $this->add($name, $values);
        } else {
            if (! $this->config->hasSection($name)) {
                throw new InvalidArgumentException(sprintf(
                    t('Can\'t update role \'%s\'. Role does not exist'),
                    $name
                ));
            }
            $this->config->setSection($name, $values);
        }
        return $this;
    }

    /**
     * (non-PHPDoc)
     * @see \Zend_Form::getValues() For the method documentation.
     */
    public function getValues($suppressArrayNotation = false)
    {
        $values = array_filter(parent::getValues($suppressArrayNotation));
        if (isset($values['permissions'])) {
            $values['permissions'] = implode(', ', $values['permissions']);
        }
        return $values;
    }
}
