<?php

add_action( 'init', 'bogo_add_rewrite_tags', 10, 0 );

function bogo_add_rewrite_tags() {
	$regex = bogo_get_lang_regex();

	if ( empty( $regex ) ) {
		return;
	}

	add_rewrite_tag( '%lang%', $regex, 'lang=' );

	$old_regex = bogo_get_prop( 'lang_rewrite_regex' );

	if ( $regex != $old_regex ) {
		bogo_set_prop( 'lang_rewrite_regex', $regex );
		flush_rewrite_rules();
	}
}


add_filter( 'date_rewrite_rules', 'bogo_date_rewrite_rules', 10, 1 );

function bogo_date_rewrite_rules( $date_rewrite ) {
	global $wp_rewrite;

	$permastruct = $wp_rewrite->get_date_permastruct();

	$permastruct = preg_replace(
		'#^' . $wp_rewrite->front . '#',
		'/%lang%' . $wp_rewrite->front,
		$permastruct
	);

	$extra = bogo_generate_rewrite_rules( $permastruct, array(
		'ep_mask' => EP_DATE,
	) );

	return array_merge( $extra, $date_rewrite );
}

add_filter( 'comments_rewrite_rules', 'bogo_comments_rewrite_rules', 10, 1 );

function bogo_comments_rewrite_rules( $comments_rewrite ) {
	global $wp_rewrite;

	$permastruct = trailingslashit( $wp_rewrite->root )
		. '%lang%/' . $wp_rewrite->comments_base;

	$extra = bogo_generate_rewrite_rules( $permastruct, array(
		'ep_mask' => EP_COMMENTS,
		'forcomments' => true,
		'walk_dirs' => false,
	) );

	return array_merge( $extra, $comments_rewrite );
}

add_filter( 'search_rewrite_rules', 'bogo_search_rewrite_rules', 10, 1 );

function bogo_search_rewrite_rules( $search_rewrite ) {
	global $wp_rewrite;

	$permastruct = trailingslashit( $wp_rewrite->root ) . '%lang%/'
		. $wp_rewrite->search_base . '/%search%';

	$extra = bogo_generate_rewrite_rules( $permastruct, array(
		'ep_mask' => EP_SEARCH,
	) );

	return array_merge( $extra, $search_rewrite );
}

add_filter( 'author_rewrite_rules', 'bogo_author_rewrite_rules', 10, 1 );

function bogo_author_rewrite_rules( $author_rewrite ) {
	global $wp_rewrite;

	$permastruct = $wp_rewrite->get_author_permastruct();

	$permastruct = preg_replace(
		'#^' . $wp_rewrite->front . '#',
		'/%lang%' . $wp_rewrite->front,
		$permastruct
	);

	$extra = bogo_generate_rewrite_rules( $permastruct, array(
		'ep_mask' => EP_AUTHORS,
	) );

	return array_merge( $extra, $author_rewrite );
}

add_filter( 'page_rewrite_rules', 'bogo_page_rewrite_rules', 10, 1 );

function bogo_page_rewrite_rules( $page_rewrite ) {
	global $wp_rewrite;

	$wp_rewrite->add_rewrite_tag( '%pagename%', '(.?.+?)', 'pagename=' );
	$permastruct = trailingslashit( $wp_rewrite->root ) . '%lang%/%pagename%';

	$extra = bogo_generate_rewrite_rules( $permastruct, array(
		'ep_mask' => EP_PAGES,
		'walk_dirs' => false,
	) );

	return array_merge( $extra, $page_rewrite );
}


add_filter( 'rewrite_rules_array', 'bogo_rewrite_rules_array', 10, 1 );

function bogo_rewrite_rules_array( $rules ) {
	global $wp_rewrite;

	$lang_regex = bogo_get_lang_regex();

	$root_rules = bogo_generate_rewrite_rules(
		path_join(
			$wp_rewrite->root,
			'/' === substr( $wp_rewrite->root, -1, 1 ) ? '%lang%/' : '%lang%'
		),
		array( 'ep_mask' => EP_ROOT )
	);

	$permastruct = $wp_rewrite->permalink_structure;

	$permastruct = preg_replace(
		'#^' . $wp_rewrite->root . '#',
		path_join(
			$wp_rewrite->root,
			'/' === substr( $wp_rewrite->root, -1, 1 ) ? '%lang%/' : '%lang%'
		),
		$permastruct
	);

	$post_rules = bogo_generate_rewrite_rules(
		$permastruct,
		array(
			'ep_mask' => EP_PERMALINK,
			'paged' => false,
		)
	);

	$localizable_post_types = bogo_localizable_post_types();

	if ( empty( $localizable_post_types ) ) {
		return $rules;
	}

	$extra_rules = array();

	foreach ( $localizable_post_types as $post_type ) {
		if ( ! $post_type_obj = get_post_type_object( $post_type )
		or false === $post_type_obj->rewrite ) {
			continue;
		}

		$permastruct = $wp_rewrite->get_extra_permastruct( $post_type );

		$permastruct = preg_replace(
			'#^' . $wp_rewrite->root . '#',
			path_join(
				$wp_rewrite->root,
				$post_type_obj->rewrite['with_front'] ? '%lang%' : '%lang%/'
			),
			$permastruct
		);

		$extra_rules += bogo_generate_rewrite_rules(
			$permastruct,
			$post_type_obj->rewrite
		);

		if ( $post_type_obj->has_archive ) {
			if ( $post_type_obj->has_archive === true ) {
				$archive_slug = $post_type_obj->rewrite['slug'];
			} else {
				$archive_slug = $post_type_obj->has_archive;
			}

			if ( $post_type_obj->rewrite['with_front'] ) {
				$archive_slug = substr( $wp_rewrite->front, 1 ) . $archive_slug;
			} else {
				$archive_slug = $wp_rewrite->root . $archive_slug;
			}

			$extra_rules += array(
				"{$lang_regex}/{$archive_slug}/?$"
					=> 'index.php?lang=$matches[1]&post_type=' . $post_type,
			);

			if ( $post_type_obj->rewrite['feeds'] and $wp_rewrite->feeds ) {
				$feeds = '(' . trim( implode( '|', $wp_rewrite->feeds ) ) . ')';

				$extra_rules += array(
					"{$lang_regex}/{$archive_slug}/feed/$feeds/?$"
						=> 'index.php?lang=$matches[1]&post_type=' . $post_type . '&feed=$matches[2]',
					"{$lang_regex}/{$archive_slug}/$feeds/?$"
						=> 'index.php?lang=$matches[1]&post_type=' . $post_type . '&feed=$matches[2]',
				);
			}

			if ( $post_type_obj->rewrite['pages'] ) {
				$extra_rules += array(
					"{$lang_regex}/{$archive_slug}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$"
						=> 'index.php?lang=$matches[1]&post_type=' . $post_type . '&paged=$matches[2]',
				);
			}
		}
	}

	$localizable_taxonomies = get_object_taxonomies(
		$localizable_post_types,
		'objects'
	);

	foreach ( $localizable_taxonomies as $taxonomy ) {
		if ( empty( $taxonomy->rewrite ) ) {
			continue;
		}

		$permastruct = $wp_rewrite->get_extra_permastruct( $taxonomy->name );

		$permastruct = preg_replace(
			'#^' . $wp_rewrite->root . '#',
			path_join(
				$wp_rewrite->root,
				$taxonomy->rewrite['with_front'] ? '%lang%' : '%lang%/'
			),
			$permastruct
		);

		$extra_rules += bogo_generate_rewrite_rules(
			$permastruct,
			$taxonomy->rewrite
		);
	}

	if ( $wp_rewrite->use_verbose_page_rules ) {
		$rules = array_merge(
			$extra_rules,
			$root_rules,
			$post_rules,
			$rules
		);
	} else {
		$rules = array_merge(
			$extra_rules,
			$root_rules,
			$post_rules,
			$rules
		);
	}

	return $rules;
}

function bogo_generate_rewrite_rules( $permalink_structure, $args = '' ) {
	global $wp_rewrite;

	$args = wp_parse_args( $args, array(
		'ep_mask' => EP_NONE,
		'paged' => true,
		'feed' => true,
		'forcomments' => false,
		'walk_dirs' => true,
		'endpoints' => true,
	) );

	$feedregex2 = '(' . implode( '|', $wp_rewrite->feeds ) . ')/?$';
	$feedregex = $wp_rewrite->feed_base . '/' . $feedregex2;
	$trackbackregex = 'trackback/?$';
	$pageregex = $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$';
	$commentregex = $wp_rewrite->comments_pagination_base . '-([0-9]{1,})/?$';
	$embedregex = 'embed/?$';

	if ( $args['endpoints'] ) {
		$ep_query_append = array();

		foreach ( (array) $wp_rewrite->endpoints as $endpoint ) {
			$epmatch = $endpoint[1] . '(/(.*))?/?$';
			$epquery = '&' . $endpoint[2] . '=';
			$ep_query_append[$epmatch] = array( $endpoint[0], $epquery );
		}
	}

	$front = substr(
		$permalink_structure,
		0,
		strpos( $permalink_structure, '%' )
	);

	preg_match_all( '/%.+?%/', $permalink_structure, $tokens );

	$index = $wp_rewrite->index;
	$feedindex = $index;
	$trackbackindex = $index;
	$embedindex = $index;

	$queries = array();

	for ( $i = 0; $i < count( $tokens[0] ); ++$i ) {
		if ( 0 < $i ) {
			$queries[$i] = $queries[$i - 1] . '&';
		} else {
			$queries[$i] = '';
		}

		$query_token = str_replace(
			$wp_rewrite->rewritecode,
			$wp_rewrite->queryreplace,
			$tokens[0][$i]
		) . $wp_rewrite->preg_index( $i + 1 );

		$queries[$i] .= $query_token;
	}

	$structure = $permalink_structure;

	if ( '/' !== $front ) {
		$structure = str_replace( $front, '', $structure );
	}

	$structure = trim( $structure, '/' );

	$dirs = $args['walk_dirs']
		? explode( '/', $structure )
		: array( $structure );

	$front = preg_replace( '|^/+|', '', $front );

	$post_rewrite = array();
	$struct = $front;

	for ( $j = 0; $j < count( $dirs ); ++$j ) {
		$struct .= $dirs[$j] . '/';
		$struct = ltrim( $struct, '/' );

		$match = str_replace(
			$wp_rewrite->rewritecode,
			$wp_rewrite->rewritereplace,
			$struct
		);

		$num_toks = preg_match_all( '/%.+?%/', $struct, $toks );

		$query = ( ! empty( $num_toks ) && isset( $queries[$num_toks - 1] ) )
			? $queries[$num_toks - 1]
			: '';

		switch ( $dirs[$j] ) {
			case '%year%':
				$ep_mask_specific = EP_YEAR;
				break;
			case '%monthnum%':
				$ep_mask_specific = EP_MONTH;
				break;
			case '%day%':
				$ep_mask_specific = EP_DAY;
				break;
			default:
				$ep_mask_specific = EP_NONE;
		}

		$pagematch = $match . $pageregex;
		$pagequery = $index . '?' . $query
			. '&paged=' . $wp_rewrite->preg_index( $num_toks + 1 );

		$commentmatch = $match . $commentregex;
		$commentquery = $index . '?' . $query
			. '&cpage=' . $wp_rewrite->preg_index( $num_toks + 1 );

		if ( get_option( 'page_on_front' ) ) {
			$rootcommentmatch = $match . $commentregex;
			$rootcommentquery = $index . '?' . $query
				. '&page_id=' . get_option( 'page_on_front' )
				. '&cpage=' . $wp_rewrite->preg_index( $num_toks + 1 );
		}

		$feedmatch = $match . $feedregex;
		$feedquery = $feedindex . '?' . $query
			. '&feed=' . $wp_rewrite->preg_index( $num_toks + 1 );

		$feedmatch2 = $match . $feedregex2;
		$feedquery2 = $feedindex . '?' . $query
			. '&feed=' . $wp_rewrite->preg_index( $num_toks + 1 );

		$embedmatch = $match . $embedregex;
		$embedquery = $embedindex . '?' . $query . '&embed=true';

		if ( $args['forcomments'] ) {
			$feedquery .= '&withcomments=1';
			$feedquery2 .= '&withcomments=1';
		}

		$rewrite = array();

		if ( $args['feed'] ) {
			$rewrite = array(
				$feedmatch => $feedquery,
				$feedmatch2 => $feedquery2,
				$embedmatch => $embedquery,
			);
		}

		if ( $args['paged'] ) {
			$rewrite = array_merge(
				$rewrite,
				array( $pagematch => $pagequery )
			);
		}

		if ( EP_PAGES & $args['ep_mask']
		or EP_PERMALINK & $args['ep_mask'] ) {
			$rewrite = array_merge(
				$rewrite,
				array( $commentmatch => $commentquery )
			);
		} elseif ( EP_ROOT & $args['ep_mask']
		and get_option( 'page_on_front' ) ) {
			$rewrite = array_merge(
				$rewrite,
				array( $rootcommentmatch => $rootcommentquery )
			);
		}

		if ( $args['endpoints'] ) {
			foreach ( (array) $ep_query_append as $regex => $ep ) {
				if ( $ep[0] & $args['ep_mask']
				or $ep[0] & $ep_mask_specific ) {
					$rewrite[$match . $regex] = $index . '?' . $query
						. $ep[1] . $wp_rewrite->preg_index( $num_toks + 2 );
				}
			}
		}

		if ( $num_toks ) {
			$post = false;
			$page = false;

			if ( strpos( $struct, '%postname%' ) !== false
			or strpos( $struct, '%post_id%' ) !== false
			or strpos( $struct, '%pagename%' ) !== false
			or ( strpos( $struct, '%year%' ) !== false
				and strpos( $struct, '%monthnum%' ) !== false
				and strpos( $struct, '%day%' ) !== false
				and strpos( $struct, '%hour%' ) !== false
				and strpos( $struct, '%minute%' ) !== false
				and strpos( $struct, '%second%' ) !== false ) ) {
				$post = true;

				if ( strpos( $struct, '%pagename%' ) !== false ) {
					$page = true;
				}
			}

			if ( ! $post ) {
				foreach ( get_post_types( array( '_builtin' => false ) ) as $ptype ) {
					if ( strpos( $struct, "%$ptype%" ) !== false ) {
						$post = true;
						$page = is_post_type_hierarchical( $ptype );
						break;
					}
				}
			}

			if ( $post ) {
				$trackbackmatch = $match . $trackbackregex;
				$trackbackquery = $trackbackindex . '?' . $query . '&tb=1';

				$embedmatch = $match . $embedregex;
				$embedquery = $embedindex . '?' . $query . '&embed=true';

				$match = rtrim( $match, '/' );
				$submatchbase = preg_replace( '/\(([^?].+?)\)/', '(?:$1)', $match );

				$sub1 = $submatchbase . '/([^/]+)/';
				$sub1tb = $sub1 . $trackbackregex;
				$sub1feed = $sub1 . $feedregex;
				$sub1feed2 = $sub1 . $feedregex2;
				$sub1comment = $sub1 . $commentregex;
				$sub1embed = $sub1 . $embedregex;

				$sub2 = $submatchbase . '/attachment/([^/]+)/';
				$sub2tb = $sub2 . $trackbackregex;
				$sub2feed = $sub2 . $feedregex;
				$sub2feed2 = $sub2 . $feedregex2;
				$sub2comment = $sub2 . $commentregex;
				$sub2embed = $sub2 . $embedregex;

				$subquery = $index . '?attachment=' . $wp_rewrite->preg_index( 1 );
				$subtbquery = $subquery . '&tb=1';
				$subfeedquery = $subquery . '&feed=' . $wp_rewrite->preg_index( 2 );
				$subcommentquery = $subquery . '&cpage=' . $wp_rewrite->preg_index( 2 );
				$subembedquery = $subquery . '&embed=true';

				if ( ! empty( $args['endpoints'] ) ) {
					foreach ( (array) $ep_query_append as $regex => $ep ) {
						if ( $ep[0] & EP_ATTACHMENT ) {
							$rewrite[$sub1 . $regex] =
								$subquery . $ep[1] . $wp_rewrite->preg_index( 3 );
							$rewrite[$sub2 . $regex] =
								$subquery . $ep[1] . $wp_rewrite->preg_index( 3 );
						}
					}
				}

				$sub1 .= '?$';
				$sub2 .= '?$';

				$match = $match . '(?:/([0-9]+))?/?$';
				$query = $index . '?' . $query
					. '&page=' . $wp_rewrite->preg_index( $num_toks + 1 );
			} else {
				$match .= '?$';
				$query = $index . '?' . $query;
			}

			$rewrite = array_merge( $rewrite, array( $match => $query ) );

			if ( $post ) {
				$rewrite = array_merge(
					array( $trackbackmatch => $trackbackquery ),
					$rewrite
				);

				$rewrite = array_merge(
					array( $embedmatch => $embedquery ),
					$rewrite
				);

				if ( ! $page ) {
					$rewrite = array_merge(
						$rewrite,
						array(
							$sub1 => $subquery,
							$sub1tb => $subtbquery,
							$sub1feed => $subfeedquery,
							$sub1feed2 => $subfeedquery,
							$sub1comment => $subcommentquery,
							$sub1embed => $subembedquery,
						)
					);
				}

				$rewrite = array_merge(
					array(
						$sub2 => $subquery,
						$sub2tb => $subtbquery,
						$sub2feed => $subfeedquery,
						$sub2feed2 => $subfeedquery,
						$sub2comment => $subcommentquery,
						$sub2embed => $subembedquery,
					),
					$rewrite
				);
			}
		}

		$post_rewrite = array_merge( $rewrite, $post_rewrite );
	}

	return $post_rewrite;
}
