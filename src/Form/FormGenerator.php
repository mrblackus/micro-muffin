<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mathieu
 * Date: 7/8/13
 * Time: 5:05 PM
 * To change this template use File | Settings | File Templates.
 */

namespace MicroMuffin\Form;

class FormGenerator
{
  /** @var string */
  private $action;

  /** @var string */
  private $method;

  /** @var Field[] */
  private $fields;

  /** @var string */
  private $legend;

  /** @var bool */
  private $isHorizontal;

  /** @var string */
  private $submitLabel;

  /** @var string */
  private $formError;

  /** @var array */
  private $buttons;

  /** @var string */
  private $fieldClass;

  /** @var bool */
  private $hasFileInput;

  /**
   * @param string $action
   * @param string $method
   */
  public function __construct($action, $method = 'POST')
  {
    $this->method       = $method;
    $this->action       = $action;
    $this->fields       = array();
    $this->legend       = null;
    $this->isHorizontal = true;
    $this->formError    = null;
    $this->submitLabel  = 'Valider';
    $this->buttons      = array();
    $this->fieldClass   = null;
    $this->hasFileInput = false;
  }

  public function setFieldClass($class)
  {
    $this->fieldClass = $class;
  }

  /**
   * @param string $name
   * @param string $href
   */
  public function addButton($name, $href)
  {
    $this->buttons[] = array('name' => $name, 'href' => $href);
  }

  /**
   * @param array $errors
   */
  public function fillErrors(&$errors)
  {
    if (!is_null($errors))
    {
      foreach ($errors as $err => $array)
      {
        if ($err == '_form')
          $this->formError = $array;
        else
        {
          if (array_key_exists($err, $this->fields))
            $this->fields[$err]->setErrors($array);
        }
      }
      $errors = null;
    }
  }

  /**
   * @param string $s
   */
  public function setSubmitLabel($s)
  {
    $this->submitLabel = $s;
  }

  /**
   * @param bool $b
   */
  public function setHorizontal($b)
  {
    $this->isHorizontal = $b;
  }

  /**
   * @param string $legend
   */
  public function setLegend($legend)
  {
    $this->legend = $legend;
  }

  /**
   * @param string $name
   * @param string $type
   * @param int $required
   * @return Input
   */
  public function addInput($name, $type, $required = Field::FIELD_OPTIONAL)
  {
    $input               = new Input($name, $type, $required);
    $this->fields[$name] = $input;
    return $input;
  }

  /**
   * @param string $name
   * @param string $label
   * @param int $required
   * @return Checkbox
   */
  public function addCheckBox($name, $label, $required = Field::FIELD_OPTIONAL)
  {
    $checkbox = new Checkbox($name, $required);
    $checkbox->setLabel($label);
    $this->fields[$name] = $checkbox;
    return $checkbox;
  }

  /**
   * @param string $name
   * @param array $options
   * @param int $required
   * @return Select
   */
  public function addSelect($name, Array $options, $required = Field::FIELD_OPTIONAL)
  {
    $select              = new Select($name, $options, $required);
    $this->fields[$name] = $select;
    return $select;
  }

  /**
   * @param string $name
   * @param int $required
   * @return Textarea
   */
  public function addTextarea($name, $required = Field::FIELD_OPTIONAL)
  {
    $textarea            = new Textarea($name, $required);
    $this->fields[$name] = $textarea;
    return $textarea;
  }

  /**
   * @param string $name
   * @param array $options
   * @param int $required
   * @return Radio
   */
  public function addRadio($name, Array $options, $required = Field::FIELD_OPTIONAL)
  {
    $radio               = new Radio($name, $options, $required);
    $this->fields[$name] = $radio;
    return $radio;
  }

  public function addFileInput($name, $required = Field::FIELD_OPTIONAL)
  {
    $this->hasFileInput  = true;
    $fileInput           = new FileInput($name, $required);
    $this->fields[$name] = $fileInput;
    return $fileInput;
  }

  /**
   * @return string
   */
  public function toString()
  {
    $requiredFields = false;
    $str            = '';
    $horizontal     = $this->isHorizontal ? ' class="form-horizontal" ' : null;

    $str .= '<form action="' . $this->action . '" method="' . $this->method . '"' . $horizontal . ' ' . ($this->hasFileInput ? 'enctype="multipart/form-data"' : '') . '>';

    if (!is_null($this->legend))
      $str .= '<fieldset><legend>' . $this->legend . '</legend>';

    if (!is_null($this->formError) && count($this->formError) > 0)
    {
      $str .= '<div class="alert alert-error">';
      foreach ($this->formError as $error)
      {
        $str .= '<div>' . $error . '</div>';
      }
      $str .= '</div>';
    }

    foreach ($this->fields as $field)
    {
      if (!is_null($this->fieldClass) && is_null($field->getClass()))
        $field->setClass($this->fieldClass);

      $requiredFields = $requiredFields || $field->isRequired();
      $str .= $field->toString();
    }

    if (!is_null($this->legend))
      $str .= '</fieldset>';

    $str .= '<div class="control-group"><div class="controls">';
    $str .= '<button type="submit" class="btn btn-primary">' . $this->submitLabel . '</button>';

    foreach ($this->buttons as $button)
      $str .= ' <a class="btn" href="' . $button['href'] . '">' . $button['name'] . '</a>';

    $str .= '</div></div>';

    if ($requiredFields)
      $str .= '<div><div class="control-group"><div class="controls">' . Field::requiredStarToString() . ' champs obligatoires</div></div></div>';

    $str .= '</form>';
    return $str;
  }
}