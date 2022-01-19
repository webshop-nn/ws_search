<?php
namespace Drupal\ws_search\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class JsonApiSearchController
 * @package Drupal\ws_search\Controller
 */
class JsonApiSearchController {

	/**
	 * @return JsonResponse
	 */
	public function response(Request $request) {
		$query_str = $request->query->get('query');
		if (!isset($query_str) || !is_string($query_str))
			$query_str = "";

		$config = \Drupal::config('ws_search.settings');

		// Форматируем подтипы
		$entity_subtypes = [];
		$entity_subtypes_raw = $config->get("material_type");
		if (isset($entity_subtypes_raw))
			foreach (explode(";",$entity_subtypes_raw) as $subtype) {
				if (strlen(trim($subtype)) <= 0) continue;
				$subtype = explode(":",$subtype);
				if (count($subtype) == 2) {
					$entity_subtypes[trim($subtype[0])][] = trim($subtype[1]);
				} else {
					$entity_subtypes["@"][] = trim($subtype[0]);
				}
			}
		unset($entity_subtypes_raw);

		$filtradeFields = [];
		$filtradeFields_raw = $config->get("filtrade_fields");
		if (isset($filtradeFields_raw))
			foreach (explode(";",$filtradeFields_raw) as $field) {
				if (strlen(trim($field)) <= 0) continue;
				$field = explode(":",$field);
				if (count($field) == 2) {
					$filtradeFields[trim($field[0])][] = trim($field[1]);
				} else {
					$filtradeFields["@"][] = trim($field[0]);
				}
			}
		unset($filtradeFields_raw);

		$fields = [];
		$fields_raw = $config->get("fields");
		if (isset($fields_raw))

			/**
			 * Если ты ничего не понял, то тут происходит следущее.
			 * Я разбиваю строку по шаблону node : field = display
			 * Таким образом, чтобы это было в массиве типа array( node, field, display )
			 * А затем добавляю в $fields таким образом чтобы было $fields[ node ][ display ] = field
			 */
			foreach (explode(";",$fields_raw) as $field) {
				if (strlen($field) <= 0 || $field === " ") continue;
				$field_arr = explode(":",$field);
				if (count($field_arr) == 2) {
					$field_arr[2] = explode("=",$field_arr[1]);
					if (count($field_arr[2]) == 2) {
						$field_arr[1] = trim($field_arr[2][0]);
						$field_arr[2] = trim($field_arr[2][1]);
						$field_arr[0] = trim($field_arr[0]);
					} else {
						unset($field_arr[2]);
					}
					$fields[$field_arr[0]][$field_arr[2]] = $field_arr[1];
				} else {
					$field_arr = explode("=",$field);
					if (count($field_arr) == 2) {
						$fields["@"][trim($field_arr[1])] = trim($field_arr[0]);
					} else {
						$fields["@"][trim($field)] = trim($field);
					}
				}
			}

		$display_mode = $config->get("fields_view");

		$entity_types = $config->get("entity_type");
		$out = [ "prompts" => [] ];
		$more = $config->get("link_more");
		if (isset($more) && strlen($more) > 0)
			$out["linkMore"] = str_replace("%QUERY%",$query_str,$more);
		$renderer = \Drupal::service('renderer');
		if (isset($entity_types)) {
			$count_types = (float)count($entity_types);
			$remainder = 0;
			foreach ($entity_types as $type) {
				$query = \Drupal::entityQuery($type);

				// Фильтр по полям
				$group = $query->orConditionGroup();

				if (isset($filtradeFields[$type]))
					foreach ($filtradeFields[$type] as $field) {
						$group->condition($field, '%'.$query_str.'%', 'LIKE');
					}
				elseif (isset($filtradeFields["@"]))
					foreach ($filtradeFields["@"] as $field) {
						$group->condition($field, '%'.$query_str.'%', 'LIKE');
					}
				else
					$group->condition('title', '%'.$query_str.'%', 'LIKE');

				$query->condition($group);

				// Конфиг
				$count = $config->get("out_count");

				// Необходимое число для вывода
				$count_needle = 0;

				// Ограничение по количеству
				if (isset($count) && ($count = (float)$count ) > 0) {
					$count_needle = $count/$count_types;
					$query->range(0,$count_needle+$remainder);
				}

				// Ограничение по подтипу
				if (isset($entity_subtypes[$type]))
					foreach ($entity_subtypes[$type] as $subtype) {
						$query->condition('type', $subtype);
					}
				elseif (isset($entity_subtypes["@"]))
					foreach ($entity_subtypes["@"] as $subtype) {
						$query->condition('type', $subtype);
					}

				// Кнопка "Не показывать"
				$btn_not_show = $config->get("button_not_show");
				if (isset($btn_not_show) && strlen($btn_not_show) > 0) {
					$group = $query->orConditionGroup();
					$group->notExists($btn_not_show);
					$group->condition($btn_not_show, 1, '<>');
				}

				// Сортировка
				if ($config->get("sort") !== null)
				switch ($config->get("sort")) {
					case "title":$query->sort("title","ASC");break;
					case "title_desc":$query->sort("title","DESC");break;
					case "creation_date":$query->sort("created","ASC");break;
					case "creation_date_desc":$query->sort("created","DESC");break;
					case "custom":
						$custom_field = $config->get("sort_custom");
						if (isset($custom_field) && strlen($custom_field) > 0)
							$query->sort($custom_field,"ASC");
						break;
					case "custom_desc":
						$custom_field = $config->get("sort_custom");
						if (isset($custom_field) && strlen($custom_field) > 0)
							$query->sort($custom_field,"DESC");
						break;
				}

				// Начинаем вывод
				$entitys = $query->execute();

				// Равномерно распределить количество выводимых элементов
				if ($count_types > 1) {
					$entitys_count = count($entitys);
					if ($entitys_count < $count_needle) {
						$remainder += $count_needle - $entitys_count;
					} elseif ($entitys_count < $count_needle + $remainder) {
						$remainder = $count_needle + $remainder - $entitys_count;
					} else {
						$remainder = 0;
					}
				}

				// Добавляем полученные материалы в вывод
				$entitys = \Drupal::entityTypeManager()->getStorage($type)->loadMultiple($entitys);
				foreach ($entitys as $entity) {
					$render = [];
					foreach ($fields["@"] as $display => $field) {
						if (isset($fields[$type][$display])) continue;
						if ($field == "url")
							$render[$display] = $entity->toUrl()->toString();
						elseif (strpos($field,"variation.") === 0) {
							$field = substr($field, 10);
							if ($entity->get("variations")->count() > 0) {
								$variation = $entity->get("variations")->get(0)->entity;
								$render[$display] = $variation->get($field)->view($display_mode);
								$render[$display] = $renderer->renderRoot($render[$display]);
							}
						} else {
							$render[$display] = $entity->get($field)->view($display_mode);
							$render[$display] = $renderer->renderRoot($render[$display]);
						}
					}
					if (isset($fields[$type]))
						foreach ($fields[$type] as $display => $field) {
							if ($field == "url")
								$render[$display] = $entity->toUrl()->toString();
							elseif (strpos($field,"variation.") === 0) {
								$field = substr($field, 10);
								if ($entity->get("variations")->count() > 0) {
									$variation = $entity->get("variations")->get(0)->entity;
									$render[$display] = $variation->get($field)->view($display_mode);
									$render[$display] = $renderer->renderRoot($render[$display]);
								}
							} else {
								$render[$display] = $entity->get($field)->view($display_mode);
								$render[$display] = $renderer->renderRoot($render[$display]);
							}
						}
					$out["prompts"][] = $render;
				}
			}
		}

		return new JsonResponse($out);
	}
}
