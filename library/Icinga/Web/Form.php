<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Web;

use LogicException;
use Zend_Config;
use Zend_Form;
use Zend_View_Interface;
use Icinga\Application\Icinga;
use Icinga\Web\Form\Decorator\NoScriptApply;
use Icinga\Web\Form\Element\CsrfCounterMeasure;

/**
 * Base class for forms providing CSRF protection, confirmation logic and auto submission
 *
 * @method $this setDefaults(array $defaults) {
 *     Use `Form::populate()' for setting default values for elements instead because `Form::setDefaults()' does not
 *     create the form via `Form::create()'.
 *
 *     Due to a BC introduced with https://github.com/mhujer/zf1/commit/244e3d3f88a363ee0ca49cf63eee31f925f515cd
 *     we cannot override this function without running into a strict standards violation on Zend version 1.12.7.
 *
 *     @param   array $defaults
 *
 *     @return  $this
 * }
 */
class Form extends Zend_Form
{
    /**
     * Whether this form has been created
     *
     * @var bool
     */
    protected $created = false;

    /**
     * The request associated with this form
     *
     * @var Request
     */
    protected $request;

    /**
     * The callback to call instead of Form::onSuccess()
     *
     * @var callable
     */
    protected $onSuccess;

    /**
     * Label to use for the standard submit button
     *
     * @var string
     */
    protected $submitLabel;

    /**
     * The url to redirect to upon success
     *
     * @var string|Url
     */
    protected $redirectUrl;

    /**
     * The view script to use when rendering this form
     *
     * @var string
     */
    protected $viewScript;

    /**
     * Whether this form should NOT add random generated "challenge" tokens that are associated with the user's current
     * session in order to prevent Cross-Site Request Forgery (CSRF). It is the form's responsibility to verify the
     * existence and correctness of this token
     *
     * @var bool
     */
    protected $tokenDisabled = false;

    /**
     * Name of the CSRF token element
     *
     * @var string
     */
    protected $tokenElementName = 'CSRFToken';

    /**
     * Whether this form should add a UID element being used to distinct different forms posting to the same action
     *
     * @var bool
     */
    protected $uidDisabled = false;

    /**
     * Name of the form identification element
     *
     * @var string
     */
    protected $uidElementName = 'formUID';

    /**
     * Whether the form should validate the sent data when being automatically submitted
     *
     * @var bool
     */
    protected $validatePartial = false;

    /**
     * Default element decorators
     *
     * @var array
     */
    public static $defaultElementDecorators = array(
        array('ViewHelper', array('separator' => '')),
        array('Errors', array('separator' => '')),
        array('Label', array('separator' => '')),
        array('HtmlTag', array('tag' => 'div', 'class' => 'element'))
    );

    /**
     * (non-PHPDoc)
     * @see \Zend_Form::construct() For the method documentation.
     */
    public function __construct($options = null)
    {
        // Zend's plugin loader reverses the order of added prefix paths thus trying our paths first before trying
        // Zend paths
        $this->addPrefixPaths(array(
            array(
                'prefix'    => 'Icinga\\Web\\Form\\Element\\',
                'path'      => Icinga::app()->getLibraryDir('Icinga/Web/Form/Element'),
                'type'      => static::ELEMENT
            ),
            array(
                'prefix'    => 'Icinga\\Web\\Form\\Decorator\\',
                'path'      => Icinga::app()->getLibraryDir('Icinga/Web/Form/Decorator'),
                'type'      => static::DECORATOR
            )
        ));

        parent::__construct($options);
    }

    /**
     * Set a callback that is called instead of this form's onSuccess method
     *
     * It is called using the following signature: (Form $this).
     *
     * @param   callable    $onSuccess  Callback
     *
     * @return  $this
     *
     * @throws  LogicException          If the callback is not callable
     */
    public function setOnSuccess($onSuccess)
    {
        if (! is_callable($onSuccess)) {
            throw new LogicException('The option `onSuccess\' is not callable');
        }
        $this->onSuccess = $onSuccess;
        return $this;
    }

    /**
     * Set the label to use for the standard submit button
     *
     * @param   string  $label  The label to use for the submit button
     *
     * @return  self
     */
    public function setSubmitLabel($label)
    {
        $this->submitLabel = $label;
        return $this;
    }

    /**
     * Return the label being used for the standard submit button
     *
     * @return  string
     */
    public function getSubmitLabel()
    {
        return $this->submitLabel;
    }

    /**
     * Set the url to redirect to upon success
     *
     * @param   string|Url  $url    The url to redirect to
     *
     * @return  self
     */
    public function setRedirectUrl($url)
    {
        $this->redirectUrl = $url;
        return $this;
    }

    /**
     * Return the url to redirect to upon success
     *
     * @return  string|Url
     */
    public function getRedirectUrl()
    {
        if ($this->redirectUrl === null) {
            $url = Url::fromRequest(array(), $this->getRequest());
            // Be sure to remove all form dependent params because we do not want to submit it again
            $this->redirectUrl = $url->without(array_keys($this->getElements()));
        }

        return $this->redirectUrl;
    }

    /**
     * Set the view script to use when rendering this form
     *
     * @param   string  $viewScript     The view script to use
     *
     * @return  self
     */
    public function setViewScript($viewScript)
    {
        $this->viewScript = $viewScript;
        return $this;
    }

    /**
     * Return the view script being used when rendering this form
     *
     * @return  string
     */
    public function getViewScript()
    {
        return $this->viewScript;
    }

    /**
     * Disable CSRF counter measure and remove its field if already added
     *
     * @param   bool    $disabled   Set true in order to disable CSRF protection for this form, otherwise false
     *
     * @return  self
     */
    public function setTokenDisabled($disabled = true)
    {
        $this->tokenDisabled = (bool) $disabled;

        if ($disabled && $this->getElement($this->tokenElementName) !== null) {
            $this->removeElement($this->tokenElementName);
        }

        return $this;
    }

    /**
     * Return whether CSRF counter measures are disabled for this form
     *
     * @return  bool
     */
    public function getTokenDisabled()
    {
        return $this->tokenDisabled;
    }

    /**
     * Set the name to use for the CSRF element
     *
     * @param   string  $name   The name to set
     *
     * @return  self
     */
    public function setTokenElementName($name)
    {
        $this->tokenElementName = $name;
        return $this;
    }

    /**
     * Return the name of the CSRF element
     *
     * @return  string
     */
    public function getTokenElementName()
    {
        return $this->tokenElementName;
    }

    /**
     * Disable form identification and remove its field if already added
     *
     * @param   bool    $disabled   Set true in order to disable identification for this form, otherwise false
     *
     * @return  self
     */
    public function setUidDisabled($disabled = true)
    {
        $this->uidDisabled = (bool) $disabled;

        if ($disabled && $this->getElement($this->uidElementName) !== null) {
            $this->removeElement($this->uidElementName);
        }

        return $this;
    }

    /**
     * Return whether identification is disabled for this form
     *
     * @return  bool
     */
    public function getUidDisabled()
    {
        return $this->uidDisabled;
    }

    /**
     * Set the name to use for the form identification element
     *
     * @param   string  $name   The name to set
     *
     * @return  self
     */
    public function setUidElementName($name)
    {
        $this->uidElementName = $name;
        return $this;
    }

    /**
     * Return the name of the form identification element
     *
     * @return  string
     */
    public function getUidElementName()
    {
        return $this->uidElementName;
    }

    /**
     * Set whether this form should validate the sent data when being automatically submitted
     *
     * @param   bool    $state
     *
     * @return  self
     */
    public function setValidatePartial($state)
    {
        $this->validatePartial = $state;
        return $this;
    }

    /**
     * Return whether this form should validate the sent data when being automatically submitted
     *
     * @return  bool
     */
    public function getValidatePartial()
    {
        return $this->validatePartial;
    }

    /**
     * Create this form
     *
     * @param   array   $formData   The data sent by the user
     *
     * @return  self
     */
    public function create(array $formData = array())
    {
        if (false === $this->created) {
            $this->createElements($formData);
            $this->addFormIdentification()
                ->addCsrfCounterMeasure()
                ->addSubmitButton();

            if ($this->getAction() === '') {
                // We MUST set an action as JS gets confused otherwise, if
                // this form is being displayed in an additional column
                $this->setAction(Url::fromRequest()->without(array_keys($this->getElements())));
            }

            $this->created = true;
        }

        return $this;
    }

    /**
     * Create and add elements to this form
     *
     * Intended to be implemented by concrete form classes.
     *
     * @param   array   $formData   The data sent by the user
     */
    public function createElements(array $formData)
    {

    }

    /**
     * Perform actions after this form was submitted using a valid request
     *
     * Intended to be implemented by concrete form classes. The base implementation returns always FALSE.
     *
     * @return  null|bool               Return FALSE in case no redirect should take place
     */
    public function onSuccess()
    {
        return false;
    }

    /**
     * Perform actions when no form dependent data was sent
     *
     * Intended to be implemented by concrete form classes.
     */
    public function onRequest()
    {

    }

    /**
     * Add a submit button to this form
     *
     * Uses the label previously set with Form::setSubmitLabel(). Overwrite this
     * method in order to add multiple submit buttons or one with a custom name.
     *
     * @return  self
     */
    public function addSubmitButton()
    {
        $submitLabel = $this->getSubmitLabel();
        if ($submitLabel) {
            $this->addElement(
                'submit',
                'btn_submit',
                array(
                    'ignore'        => true,
                    'label'         => $submitLabel,
                    'decorators'    => array(
                        'ViewHelper',
                        array('HtmlTag', array('tag' => 'div'))
                    )
                )
            );
        }

        return $this;
    }

    /**
     * Add a subform
     *
     * @param   Zend_Form   $form   The subform to add
     * @param   string      $name   The name of the subform or null to use the name of $form
     * @param   int         $order  The location where to insert the form
     *
     * @return  Zend_Form
     */
    public function addSubForm(Zend_Form $form, $name = null, $order = null)
    {
        if ($form instanceof self) {
            $form->removeDecorator('Form');
            $form->setSubmitLabel('');
            $form->setTokenDisabled();
            $form->setUidDisabled();
        }

        if ($name === null) {
            $name = $form->getName();
        }

        return parent::addSubForm($form, $name, $order);
    }

    /**
     * Create a new element
     *
     * Icinga Web 2 loads its own default element decorators. For loading Zend's default element decorators set the
     * `disableLoadDefaultDecorators' option to any other value than `true'. For loading custom element decorators use
     * the 'decorators' option.
     *
     * @param   string  $type       The type of the element
     * @param   string  $name       The name of the element
     * @param   mixed   $options    The options for the element
     *
     * @return  Zend_Form_Element
     *
     * @see     Form::$defaultElementDecorators For Icinga Web 2's default element decorators.
     */
    public function createElement($type, $name, $options = null)
    {
        if ($options !== null) {
            if ($options instanceof Zend_Config) {
                $options = $options->toArray();
            }
            if (! isset($options['decorators'])
                && ! array_key_exists('disabledLoadDefaultDecorators', $options)
            ) {
                $options['decorators'] = static::$defaultElementDecorators;
            }
        } else {
            $options = array('decorators' => static::$defaultElementDecorators);
        }

        $el = parent::createElement($type, $name, $options);

        if (($description = $el->getDescription()) !== null && ($label = $el->getDecorator('label')) !== null) {
            $label->setOptions(array(
                'title' => $description,
                'class' => 'has-feedback'
            ));
        }

        if ($el->getAttrib('autosubmit')) {
            $noScript = new NoScriptApply(); // Non-JS environments
            $decorators = $el->getDecorators();
            $pos = array_search('Zend_Form_Decorator_ViewHelper', array_keys($decorators)) + 1;
            $el->setDecorators(
                array_slice($decorators, 0, $pos, true)
                + array(get_class($noScript) => $noScript)
                + array_slice($decorators, $pos, count($decorators) - $pos, true)
            );

            $class = $el->getAttrib('class');
            if (is_array($class)) {
                $class[] = 'autosubmit';
            } elseif ($class === null) {
                $class = 'autosubmit';
            } else {
                $class .= ' autosubmit';
            }
            $el->setAttrib('class', $class); // JS environments

            unset($el->autosubmit);
        }

        return $el;
    }

    /**
     * Add a field with a unique and form specific ID
     *
     * @return  self
     */
    public function addFormIdentification()
    {
        if (false === $this->uidDisabled && $this->getElement($this->uidElementName) === null) {
            $this->addElement(
                'hidden',
                $this->uidElementName,
                array(
                    'ignore'        => true,
                    'value'         => $this->getName(),
                    'decorators'    => array('ViewHelper')
                )
            );
        }

        return $this;
    }

    /**
     * Add CSRF counter measure field to this form
     *
     * @return  self
     */
    public function addCsrfCounterMeasure()
    {
        if (false === $this->tokenDisabled && $this->getElement($this->tokenElementName) === null) {
            $this->addElement(new CsrfCounterMeasure($this->tokenElementName));
        }

        return $this;
    }

    /**
     * Populate the elements with the given values
     *
     * @param   array   $defaults   The values to populate the elements with
     */
    public function populate(array $defaults)
    {
        $this->create($defaults);
        return parent::populate($defaults);
    }

    /**
     * Process the given request using this form
     *
     * Redirects to the url set with setRedirectUrl() upon success. See onSuccess()
     * and onRequest() wherewith you can customize the processing logic.
     *
     * @param   Request     $request    The request to be processed
     *
     * @return  Request                 The request supposed to be processed
     */
    public function handleRequest(Request $request = null)
    {
        if ($request === null) {
            $request = $this->getRequest();
        } else {
            $this->request = $request;
        }

        $formData = $this->getRequestData();
        if ($this->getUidDisabled() || $this->wasSent($formData)) {
            $this->populate($formData); // Necessary to get isSubmitted() to work
            if (! $this->getSubmitLabel() || $this->isSubmitted()) {
                if ($this->isValid($formData)
                    && (($this->onSuccess !== null && false !== call_user_func($this->onSuccess, $this))
                        || ($this->onSuccess === null && false !== $this->onSuccess()))) {
                    $this->getResponse()->redirectAndExit($this->getRedirectUrl());
                }
            } elseif ($this->getValidatePartial()) {
                // The form can't be processed but we may want to show validation errors though
                $this->isValidPartial($formData);
            }
        } else {
            $this->onRequest();
        }

        return $request;
    }

    /**
     * Return whether the submit button of this form was pressed
     *
     * When overwriting Form::addSubmitButton() be sure to overwrite this method as well.
     *
     * @return  bool                True in case it was pressed, False otherwise or no submit label was set
     */
    public function isSubmitted()
    {
        if ($this->getSubmitLabel()) {
            return $this->getElement('btn_submit')->isChecked();
        }

        return false;
    }

    /**
     * Return whether the data sent by the user refers to this form
     *
     * Ensures that the correct form gets processed in case there are multiple forms
     * with equal submit button names being posted against the same route.
     *
     * @param   array   $formData   The data sent by the user
     *
     * @return  bool                Whether the given data refers to this form
     */
    public function wasSent(array $formData)
    {
        return isset($formData[$this->uidElementName]) && $formData[$this->uidElementName] === $this->getName();
    }

    /**
     * Return whether the given values (possibly incomplete) are valid
     *
     * Unlike Zend_Form::isValid() this will not set NULL as value for
     * an element that is not present in the given data.
     *
     * @param   array   $formData   The data to validate
     *
     * @return  bool
     */
    public function isValidPartial(array $formData)
    {
        $this->create($formData);

        // Ensure that disabled elements are not overwritten (http://www.zendframework.com/issues/browse/ZF-6909)
        foreach ($this->getElements() as $name => $element) {
            if ($element->getAttrib('disabled')) {
                $formData[$name] = $element->getValue();
            }
        }

        return parent::isValidPartial($formData);
    }

    /**
     * Return whether the given values are valid
     *
     * @param   array   $formData   The data to validate
     *
     * @return  bool
     */
    public function isValid($formData)
    {
        $this->create($formData);

        // Ensure that disabled elements are not overwritten (http://www.zendframework.com/issues/browse/ZF-6909)
        foreach ($this->getElements() as $name => $element) {
            if ($element->getAttrib('disabled')) {
                $formData[$name] = $element->getValue();
            }
        }

        return parent::isValid($formData);
    }

    /**
     * Remove all elements of this form
     *
     * @return  self
     */
    public function clearElements()
    {
        $this->created = false;
        return parent::clearElements();
    }

    /**
     * Load the default decorators
     *
     * Overwrites Zend_Form::loadDefaultDecorators to avoid having
     * the HtmlTag-Decorator added and to provide viewscript usage
     *
     * @return  self
     */
    public function loadDefaultDecorators()
    {
        if ($this->loadDefaultDecoratorsIsDisabled()) {
            return $this;
        }

        $decorators = $this->getDecorators();
        if (empty($decorators)) {
            if ($this->viewScript) {
                $this->addDecorator('ViewScript', array(
                    'viewScript'    => $this->viewScript,
                    'form'          => $this
                ));
            } else {
                $this->addDecorator('FormErrors', array('onlyCustomFormErrors' => true))
                    ->addDecorator('FormElements')
                    //->addDecorator('HtmlTag', array('tag' => 'dl', 'class' => 'zend_form'))
                    ->addDecorator('Form');
            }
        }

        return $this;
    }

    /**
     * Return the name of this form
     *
     * @return  string
     */
    public function getName()
    {
        $name = parent::getName();
        if (! $name) {
            $name = get_class($this);
            $this->setName($name);
            $name = parent::getName();
        }
        return $name;
    }

    /**
     * Return the request associated with this form
     *
     * Returns the global request if none has been set for this form yet.
     *
     * @return  Request
     */
    public function getRequest()
    {
        if ($this->request === null) {
            $this->request = Icinga::app()->getFrontController()->getRequest();
        }

        return $this->request;
    }

    /**
     * Return the current Response
     *
     * @return  Response
     */
    public function getResponse()
    {
        return Icinga::app()->getFrontController()->getResponse();
    }

    /**
     * Return the request data based on this form's request method
     *
     * @return  array
     */
    protected function getRequestData()
    {
        if (strtolower($this->request->getMethod()) === $this->getMethod()) {
            return $this->request->{'get' . ($this->request->isPost() ? 'Post' : 'Query')}();
        }

        return array();
    }

    /**
     * Render this form
     *
     * @param   Zend_View_Interface     $view   The view context to use
     *
     * @return  string
     */
    public function render(Zend_View_Interface $view = null)
    {
        $this->create();
        return parent::render($view);
    }
}
