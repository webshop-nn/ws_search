<?php
namespace Drupal\ws_search\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
class SettingsForm extends ConfigFormBase {
	/**
	 * @return string
	 */
	public function getFormId() {
		return 'ws_search_settings';
	}
	/**
	 * Gets the configuration names that will be editable.
	 *
	 * @return array
	 *   An array of configuration object names that are editable if called in
	 *   conjunction with the trait's config() method.
	 */
	protected function getEditableConfigNames() {
		return [
			'ws_search.settings',
		];
	}
	/**
	 * @param array $form
	 * @param FormStateInterface $form_state
	 * @return array
	 */
	public function buildForm(array $form, FormStateInterface $form_state) {
		$config = $this->config('ws_search.settings');

		$form['entity_type'] = [
			'#type'          => 'select',
			'#title'         => $this->t('Тип сущности'),
			'#default_value' => $config->get('entity_type'),
			'#multiple'      => true,
			'#required'      => true,
			'#options'       => [
				"node"             => "node",
				"commerce_product" => "commerce_product",
				"taxonomy_term"    => "taxonomy_term"
			]
		];
		$form['material_type'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Подтип сущности'),
			'#description'   => $this->t('Фильтр по типу материала. Можно несколько, разделяя точкой с запятой. Если выбрано несколько типов сущностей, можно разделять типы: "node:article; commerce_product:default"'),
			'#default_value' => $config->get('material_type'),
		];
		$form['out_count'] = [
			'#type'          => 'number',
			'#title'         => $this->t('Количество выводимых объектов'),
			'#description'   => $this->t('Необходимо для ограничения количества выводимого результата. Если несколько типов, то все типы равномерно распределятся под это число.'),
			'#required'      => true,
			'#default_value' => $config->get('out_count'),
		];
		$form['sort'] = [
			'#type'          => 'select',
			'#title'         => $this->t('Сортировка'),
			'#description'   => $this->t('Варианты сортировки выводимых значений.'),
			'#default_value' => $config->get('sort'),
			'#required'      => true,
			'#options'       => [
				"default"            => "Без сортировки",
				"title"              => "Заголовок",
				"title_desc"         => "Заголовок (обратный порядок)",
				"creation_date"      => "Дата создания",
				"creation_date_desc" => "Дата создания (обратный порядок)",
				"custom"             => "Свое поле",
				"custom_desc"        => "Свое поле (обратный порядок)"
			]
		];
		$form['sort_custom'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Своя сортировка'),
			'#description'   => $this->t('Код собственного поля для кортировки.'),
			'#default_value' => $config->get('sort_custom'),
		];
		$form['fields'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Выводимые поля'),
			'#description'   => $this->t('Можно несколько, разделяя точкой с запятой. Если выбрано несколько типов сущностей, можно разделять типы: "title; node:field_image = image; commerce_product:field_logo = image"'),
			'#required'      => true,
			'#default_value' => $config->get('fields'),
		];
		$form['fields_view'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Режим просмотра полей'),
			'#description'   => $this->t('Например можно ввести teaser'),
			'#required'      => true,
			'#default_value' => $config->get('fields_view'),
		];
		$form['link_more'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Ссылка "Ещё"'),
			'#default_value' => $config->get('link_more'),
		];
		$form['button_not_show'] = [
			'#type'          => 'textfield',
			'#title'         => $this->t('Кнопка "Не показывать"'),
			'#description'   => $this->t('Код числового поля, которое будет отвечать - участвует ли материал в выдаче. Оставьте пустым, если эта кнопка не требуется.'),
			'#default_value' => $config->get('button_not_show'),
		];

		return parent::buildForm($form, $form_state);
	}
	/**
	 * @param array $form
	 * @param FormStateInterface $form_state
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
		$config = $this->config('ws_search.settings');
		$config->set('entity_type',     $form_state->getValue('entity_type'))->save();
		$config->set('material_type',   $form_state->getValue('material_type'))->save();
		$config->set('out_count',       $form_state->getValue('out_count'))->save();
		$config->set('button_not_show', $form_state->getValue('button_not_show'))->save();
		$config->set('sort',            $form_state->getValue('sort'))->save();
		$config->set('sort_custom',     $form_state->getValue('sort_custom'))->save();
		$config->set('fields',          $form_state->getValue('fields'))->save();
		$config->set('fields_view',     $form_state->getValue('fields_view'))->save();
		$config->set('link_more',       $form_state->getValue('link_more'))->save();
	}
}