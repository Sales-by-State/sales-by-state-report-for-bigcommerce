/**
 * Sales by State Report for BigCommerce.
 *
 * @package SalesByStateReportForBigCommerce
 */

( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.components || ! wp.i18n || ! wp.apiFetch || ! wp.url ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var __ = wp.i18n.__;

	var CheckboxControl = wp.components.CheckboxControl;
	var Dropdown = wp.components.Dropdown;
	var Button = wp.components.Button;
	var Card = wp.components.Card || 'div';
	var CardHeader = wp.components.CardHeader || 'div';
	var CardFooter = wp.components.CardFooter || 'div';

	var settings = config || {};
	var measures = settings.measures || [];
	var statusOptions = settings.statuses || [];
	var perPageOptions = settings.perPageOptions || [ 10, 25, 50, 100 ];

	var API = '/sbsbc/v1/report';
	var DIAGNOSTICS = '/sbsbc/v1/diagnostics';
	var BACKFILL = '/sbsbc/v1/backfill';
	var DEFAULT_PER_PAGE = 25;
	var TEXTDOMAIN = 'sales-by-state-report-for-bigcommerce';

	/**
	 * Chevron used by sort headers and pagination.
	 *
	 * @param {Object} props Component props.
	 * @return {Object} Element.
	 */
	function Chevron( props ) {
		var down = 'down' === props.direction;
		var left = 'left' === props.direction;
		var d = 'M7 14.5l5-5 5 5H7z';

		if ( down ) {
			d = 'M7 9.5l5 5 5-5H7z';
		} else if ( left ) {
			d = 'M14.5 7l-5 5 5 5V7z';
		} else if ( 'right' === props.direction ) {
			d = 'M9.5 7l5 5-5 5V7z';
		}

		return el(
			'svg',
			{
				xmlns: 'http://www.w3.org/2000/svg',
				viewBox: '0 0 24 24',
				width: 18,
				height: 18,
				'aria-hidden': 'true',
				focusable: 'false'
			},
			el( 'path', { d: d, fill: 'currentColor' } )
		);
	}

	/**
	 * WooCommerce Admin SummaryNumber lookalike.
	 *
	 * @param {Object} props Component props.
	 * @return {Object} Element.
	 */
	function SummaryNumber( props ) {
		return el(
			'li',
			{ className: 'woocommerce-summary__item-container' },
			el(
				'a',
				{
					className: 'woocommerce-summary__item',
					href: '#sbsbc-root',
					onClick: function ( event ) {
						event.preventDefault();
					}
				},
				el( 'div', { className: 'woocommerce-summary__item-label' }, props.label ),
				el(
					'div',
					{ className: 'woocommerce-summary__item-data' },
					el( 'div', { className: 'woocommerce-summary__item-value' }, props.value )
				)
			)
		);
	}

	/**
	 * WooCommerce Admin SummaryList lookalike.
	 *
	 * @param {Object} props Component props.
	 * @return {Object} Element.
	 */
	function SummaryList( props ) {
		var items = typeof props.children === 'function' ? props.children() : props.children;
		var count = Array.isArray( items ) ? items.length : 1;

		return el(
			'ul',
			{ className: 'woocommerce-summary has-' + count + '-items' },
			items
		);
	}

	/**
	 * WooCommerce Admin TableCard lookalike.
	 *
	 * @param {Object} props Component props.
	 * @return {Object} Element.
	 */
	function TableCard( props ) {
		var headers = props.headers || [];
		var rows = props.rows || [];
		var query = props.query || {};
		var orderby = query.orderby || '';
		var order = query.order || '';
		var perPage = props.rowsPerPage || DEFAULT_PER_PAGE;
		var totalRows = props.totalRows || 0;
		var pages = Math.max( 1, Math.ceil( totalRows / perPage ) );
		var page = Math.min( Math.max( 1, parseInt( query.paged, 10 ) || 1 ), pages );
		var start = totalRows ? ( page - 1 ) * perPage + 1 : 0;
		var end = Math.min( page * perPage, totalRows );
		var onQueryChange = props.onQueryChange;

		function sort( key ) {
			var nextOrder = 'desc';

			if ( orderby === key ) {
				nextOrder = 'asc' === order ? 'desc' : 'asc';
			} else if ( 'state' === key ) {
				nextOrder = 'asc';
			}

			onQueryChange( 'sort' )( key, nextOrder );
		}

		var head = el(
			'thead',
			null,
			el(
				'tr',
				null,
				headers.map( function ( header ) {
					var isSorted = orderby === header.key;
					var classes = [ 'woocommerce-table__header' ];

					if ( header.isLeftAligned ) {
						classes.push( 'is-left-aligned' );
					}

					if ( header.isNumeric ) {
						classes.push( 'is-numeric' );
					}

					if ( header.isSortable ) {
						classes.push( 'is-sortable' );
					}

					if ( isSorted ) {
						classes.push( 'is-sorted' );
					}

					var label = header.isSortable
						? el(
							Button,
							{
								className: 'is-button',
								onClick: function () {
									sort( header.key );
								}
							},
							header.label,
							el( Chevron, { direction: isSorted && 'asc' === order ? 'up' : 'down' } )
						)
						: header.label;

					return el(
						'th',
						{
							key: header.key,
							className: classes.join( ' ' ),
							role: header.isSortable ? 'columnheader' : undefined,
							'aria-sort': header.isSortable
								? ( isSorted ? ( 'asc' === order ? 'ascending' : 'descending' ) : 'none' )
								: undefined
						},
						label
					);
				} )
			)
		);

		var bodyRows;

		if ( props.isLoading ) {
			bodyRows = [ 0, 1, 2, 3, 4 ].map( function ( i ) {
				return el(
					'tr',
					{ key: 'p-' + i },
					headers.map( function ( header ) {
						return el(
							'td',
							{
								key: header.key,
								className: 'woocommerce-table__item' + ( header.isLeftAligned ? ' is-left-aligned' : '' )
							},
							el( 'span', { className: 'is-placeholder' } )
						);
					} )
				);
			} );
		} else if ( ! rows.length ) {
			bodyRows = [
				el(
					'tr',
					{ key: 'empty' },
					el(
						'td',
						{
							className: 'woocommerce-table__empty-item',
							colSpan: headers.length
						},
						__( 'No data to display', TEXTDOMAIN )
					)
				)
			];
		} else {
			bodyRows = rows.map( function ( row, index ) {
				return el(
					'tr',
					{ key: index },
					row.map( function ( cell, cellIndex ) {
						var header = headers[ cellIndex ] || {};
						var classes = [ 'woocommerce-table__item' ];

						if ( header.isLeftAligned ) {
							classes.push( 'is-left-aligned' );
						}

						if ( header.isNumeric ) {
							classes.push( 'is-numeric' );
						}

						if ( orderby === header.key ) {
							classes.push( 'is-sorted' );
						}

						return el( 'td', { key: cellIndex, className: classes.join( ' ' ) }, cell.display );
					} )
				);
			} );
		}

		var rangeLabel = totalRows
			? start + '-' + end + ' of ' + totalRows
			: '0 of 0';

		var pagination = el(
			'div',
			{ className: 'woocommerce-pagination' },
			el(
				'div',
				{ className: 'woocommerce-pagination__page-arrows' },
				el( 'span', { className: 'woocommerce-pagination__page-arrows-label' }, rangeLabel ),
				el(
					'div',
					{ className: 'woocommerce-pagination__page-arrows-buttons' },
					el(
						Button,
						{
							disabled: page <= 1 || props.isLoading,
							onClick: function () {
								onQueryChange( 'paged' )( page - 1 );
							},
							'aria-label': __( 'Previous Page', TEXTDOMAIN )
						},
						el( Chevron, { direction: 'left' } )
					),
					el(
						Button,
						{
							disabled: page >= pages || props.isLoading,
							onClick: function () {
								onQueryChange( 'paged' )( page + 1 );
							},
							'aria-label': __( 'Next Page', TEXTDOMAIN )
						},
						el( Chevron, { direction: 'right' } )
					)
				)
			),
			el(
				'div',
				{ className: 'woocommerce-pagination__page-picker' },
				el( 'span', null, __( 'Page', TEXTDOMAIN ) ),
				el( 'input', {
					className: 'woocommerce-pagination__page-picker-input',
					type: 'number',
					min: 1,
					max: pages,
					value: page,
					onChange: function ( event ) {
						var next = parseInt( event.target.value, 10 );

						if ( next >= 1 && next <= pages ) {
							onQueryChange( 'paged' )( next );
						}
					}
				} ),
				el( 'span', null, __( 'of', TEXTDOMAIN ) + ' ' + pages )
			),
			el(
				'div',
				{ className: 'woocommerce-pagination__per-page-picker' },
				el(
					'select',
					{
						value: String( perPage ),
						onChange: function ( event ) {
							onQueryChange( 'per_page' )( parseInt( event.target.value, 10 ) );
						},
						'aria-label': __( 'Rows per page', TEXTDOMAIN )
					},
					perPageOptions.map( function ( n ) {
						return el( 'option', { key: n, value: String( n ) }, n );
					} )
				),
				el( 'span', null, __( 'per page', TEXTDOMAIN ) )
			)
		);

		return el(
			Card,
			{ className: 'woocommerce-table woocommerce-analytics__card' },
			el( CardHeader, null, el( 'h2', { className: 'woocommerce-table__caption' }, props.title ) ),
			el(
				'div',
				{ className: 'woocommerce-table__table' },
				el( 'table', null, head, el( 'tbody', null, bodyRows ) )
			),
			el( CardFooter, { className: 'woocommerce-table__footer' }, pagination )
		);
	}

	/**
	 * Progress of the one-off import of orders that pre-date the plugin.
	 *
	 * @param {Object} props Component props.
	 * @return {Object|null} Element.
	 */
	function ImportStatus( props ) {
		var diag = props.diagnostics;

		if ( ! diag ) {
			return null;
		}

		if ( ! diag.table_exists ) {
			return el(
				'div',
				{ className: 'sbsbc-notice is-error' },
				el( 'p', { className: 'sbsbc-notice__title' }, __( 'The report table is missing.', TEXTDOMAIN ) ),
				el( 'p', null, __( 'Deactivate and reactivate the plugin to create it. If it still does not appear, the database user may not have permission to create tables.', TEXTDOMAIN ) )
			);
		}

		if ( diag.remaining <= 0 ) {
			return null;
		}

		var done = Math.max( 0, diag.orders - diag.remaining );
		var pct = props.running
			? props.progress
			: ( diag.orders > 0 ? Math.round( ( done / diag.orders ) * 100 ) : 0 );

		return el(
			'div',
			{ className: 'sbsbc-importing', role: 'status', 'aria-live': 'polite' },
			el(
				'div',
				{ className: 'sbsbc-importing__row' },
				el( 'span', { className: 'sbsbc-importing__label' }, __( 'Reading past orders…', TEXTDOMAIN ) ),
				el( 'span', { className: 'sbsbc-importing__count' }, pct + '%' )
			),
			el(
				'div',
				{ className: 'sbsbc-progress' },
				el( 'div', { className: 'sbsbc-progress__bar', style: { width: pct + '%' } } )
			),
			el(
				'p',
				{ className: 'sbsbc-importing__hint' },
				__( 'Figures below are incomplete until this finishes.', TEXTDOMAIN )
			)
		);
	}

	/**
	 * Read the selected statuses from the URL.
	 *
	 * @param {Object} query Current query.
	 * @return {Array} Status keys.
	 */
	function readStatuses( query ) {
		var raw = query && query.sbsbcStatuses;

		if ( ! raw ) {
			return ( settings.defaultStatuses || [ 'paid' ] ).slice();
		}

		return String( raw ).split( ',' ).filter( function ( s ) {
			return !! s;
		} );
	}

	/**
	 * Build the onQueryChange handler TableCard expects.
	 *
	 * @param {Function} setQuery Query setter.
	 * @return {Function} Handler factory.
	 */
	function makeOnQueryChange( setQuery ) {
		return function ( param ) {
			return function ( value, extra ) {
				if ( 'sort' === param ) {
					setQuery( { orderby: value, order: extra } );
					return;
				}

				if ( 'paged' === param ) {
					setQuery( { paged: value } );
					return;
				}

				if ( 'per_page' === param ) {
					setQuery( { per_page: value, paged: 1 } );
					return;
				}

				var next = {};
				next[ param ] = value;
				setQuery( next );
			};
		};
	}

	/**
	 * Sort rows in the browser.
	 *
	 * @param {Array}  rows    Rows.
	 * @param {string} orderby Column key, or 'state'.
	 * @param {string} order   asc or desc.
	 * @return {Array} Sorted rows.
	 */
	function sortRows( rows, orderby, order ) {
		if ( ! orderby ) {
			return rows;
		}

		var direction = 'asc' === order ? 1 : -1;
		var copy = rows.slice();

		if ( 'state' === orderby ) {
			copy.sort( function ( a, b ) {
				return direction * String( a.state_name ).localeCompare( String( b.state_name ) );
			} );

			return copy;
		}

		copy.sort( function ( a, b ) {
			var left = Number( a[ orderby ] ) || 0;
			var right = Number( b[ orderby ] ) || 0;

			if ( left === right ) {
				return String( a.state_name ).localeCompare( String( b.state_name ) );
			}

			return left < right ? direction : -direction;
		} );

		return copy;
	}

	/**
	 * The order status filter, as a dropdown of checkboxes.
	 *
	 * @param {Object} props Component props.
	 * @return {Object} Element.
	 */
	function StatusFilter( props ) {
		var selected = props.value;
		var label;

		if ( 1 === selected.length ) {
			var match = statusOptions.filter( function ( o ) {
				return o.value === selected[ 0 ];
			} )[ 0 ];

			label = match ? match.label : selected[ 0 ];
		} else if ( selected.length === statusOptions.length ) {
			label = __( 'All statuses', TEXTDOMAIN );
		} else {
			label = selected.length + ' ' + __( 'statuses selected', TEXTDOMAIN );
		}

		function toggle( value, checked ) {
			var next = selected.filter( function ( s ) {
				return s !== value;
			} );

			if ( checked ) {
				next = next.concat( [ value ] );
			}

			if ( ! next.length ) {
				return;
			}

			next = statusOptions
				.map( function ( o ) {
					return o.value;
				} )
				.filter( function ( v ) {
					return next.indexOf( v ) !== -1;
				} );

			props.onChange( next );
		}

		return el(
			'div',
			{ className: 'sbsbc-filter' },
			el( 'span', { className: 'sbsbc-filter__label' }, __( 'Order status', TEXTDOMAIN ) ),
			el( Dropdown, {
				className: 'sbsbc-filter__dropdown',
				contentClassName: 'sbsbc-status-popover',
				popoverProps: { placement: 'bottom-start' },
				position: 'bottom left',
				renderToggle: function ( toggleProps ) {
					return el(
						Button,
						{
							className: 'sbsbc-filter__toggle',
							onClick: toggleProps.onToggle,
							'aria-expanded': toggleProps.isOpen
						},
						el( 'span', null, label )
					);
				},
				renderContent: function () {
					return el(
						'div',
						{ className: 'sbsbc-status-popover__list' },
						statusOptions.map( function ( option ) {
							var checked = selected.indexOf( option.value ) !== -1;

							return el( CheckboxControl, {
								key: option.value,
								label: option.label,
								checked: checked,
								disabled: checked && 1 === selected.length,
								onChange: function ( isChecked ) {
									toggle( option.value, isChecked );
								}
							} );
						} )
					);
				}
			} )
		);
	}

	/**
	 * A labelled select that writes its value into the URL.
	 *
	 * @param {Object} props Component props.
	 * @return {Object} Element.
	 */
	function Filter( props ) {
		return el(
			'label',
			{ className: 'sbsbc-filter' },
			el( 'span', { className: 'sbsbc-filter__label' }, props.label ),
			el(
				'select',
				{
					className: 'sbsbc-filter__select',
					value: props.value,
					onChange: function ( event ) {
						props.onChange( event.target.value );
					}
				},
				( props.options || [] ).map( function ( option ) {
					return el( 'option', { key: option.value, value: option.value }, option.label );
				} )
			)
		);
	}

	/**
	 * The report.
	 *
	 * @param {Object} props Component props.
	 * @return {Object} Element.
	 */
	function SalesByStateReport( props ) {
		var query = props.query || {};
		var onQueryChange = makeOnQueryChange( props.setQuery );

		function setFilter( next ) {
			next.paged = 1;
			props.setQuery( next );
		}

		var dataState = useState( { rows: [], totals: null, loading: true, error: null } );
		var data = dataState[ 0 ];
		var setData = dataState[ 1 ];

		var diagState = useState( null );
		var diagnostics = diagState[ 0 ];
		var setDiagnostics = diagState[ 1 ];

		var importState = useState( { running: false, progress: 0 } );
		var importing = importState[ 0 ];
		var setImporting = importState[ 1 ];

		var reloadState = useState( 0 );
		var reload = reloadState[ 0 ];
		var setReload = reloadState[ 1 ];

		var started = useRef( false );

		var country = query.sbsbcCountry || settings.defaultCountry || 'US';
		var year = query.sbsbcYear || settings.defaultYear;
		var statuses = readStatuses( query );
		var statusKey = statuses.join( ',' );

		var orderby = query.orderby || '';
		var order = query.order || '';
		var perPage = Math.max( 1, parseInt( query.per_page, 10 ) || DEFAULT_PER_PAGE );

		useEffect(
			function () {
				var cancelled = false;

				setData( function ( current ) {
					return {
						rows: current.rows,
						totals: current.totals,
						loading: true,
						error: null
					};
				} );

				wp.apiFetch( {
					path: wp.url.addQueryArgs( API, {
						year: year,
						country: country,
						statuses: statusKey
					} )
				} )
					.then( function ( result ) {
						if ( ! cancelled ) {
							setData( {
								rows: result.rows || [],
								totals: result.totals || null,
								loading: false,
								error: null
							} );
						}
					} )
					.catch( function ( err ) {
						if ( ! cancelled ) {
							setData( {
								rows: [],
								totals: null,
								loading: false,
								error: ( err && err.message ) || __( 'Could not load the report.', TEXTDOMAIN )
							} );
						}
					} );

				return function () {
					cancelled = true;
				};
			},
			[ year, country, statusKey, reload ]
		);

		useEffect(
			function () {
				var cancelled = false;

				wp.apiFetch( { path: DIAGNOSTICS } )
					.then( function ( result ) {
						if ( ! cancelled ) {
							setDiagnostics( result );
						}
					} )
					.catch( function () {
						if ( ! cancelled ) {
							setDiagnostics( null );
						}
					} );

				return function () {
					cancelled = true;
				};
			},
			[ reload ]
		);

		useEffect(
			function () {
				if ( ! diagnostics || ! settings.canBuild ) {
					return;
				}

				if ( ! diagnostics.table_exists || diagnostics.remaining <= 0 ) {
					return;
				}

				if ( importing.running || started.current ) {
					return;
				}

				started.current = true;
				runImport();
			},
			[ diagnostics ]
		);

		/**
		 * Loop the import endpoint until nothing remains.
		 */
		function runImport() {
			setImporting( { running: true, progress: 0 } );

			var total = diagnostics ? diagnostics.orders : 0;
			var lastRemaining = diagnostics ? diagnostics.remaining : null;
			var emptyStreak = 0;

			function finish() {
				setImporting( { running: false, progress: 100 } );
				setReload( function ( n ) {
					return n + 1;
				} );
			}

			function step() {
				wp.apiFetch( { path: BACKFILL, method: 'POST', data: { limit: 10 } } )
					.then( function ( result ) {
						var remaining = result.remaining;
						var pct = total > 0
							? Math.min( 100, Math.round( ( ( total - remaining ) / total ) * 100 ) )
							: 0;

						setImporting( { running: true, progress: pct } );

						if ( result.complete ) {
							finish();
							return;
						}

						if ( 0 === result.processed && remaining === lastRemaining ) {
							emptyStreak += 1;
						} else {
							emptyStreak = 0;
						}

						lastRemaining = remaining;

						if ( emptyStreak >= 8 ) {
							finish();
							return;
						}

						if ( 0 === result.processed ) {
							window.setTimeout( step, 1500 );
							return;
						}

						step();
					} )
					.catch( function () {
						setImporting( { running: false, progress: 0 } );
					} );
			}

			step();
		}

		var sorted = sortRows( data.rows, orderby, order );
		var pages = Math.max( 1, Math.ceil( sorted.length / perPage ) );
		var page = Math.min( Math.max( 1, parseInt( query.paged, 10 ) || 1 ), pages );
		var pageRows = sorted.slice( ( page - 1 ) * perPage, page * perPage );

		var headers = [
			{
				key: 'state',
				label: __( 'State', TEXTDOMAIN ),
				isLeftAligned: true,
				required: true,
				isSortable: true
			}
		].concat(
			measures.map( function ( m ) {
				return {
					key: m.key,
					label: m.label,
					isNumeric: true,
					isSortable: true,
					required: true
				};
			} )
		);

		var rows = pageRows.map( function ( row ) {
			return [ { display: row.state_name, value: row.state_name } ].concat(
				measures.map( function ( m ) {
					return {
						display: row[ m.key + '_formatted' ],
						value: row[ m.key ]
					};
				} )
			);
		} );

		var summary = data.totals
			? el( SummaryList, null, function () {
				return measures.map( function ( m ) {
					return el( SummaryNumber, {
						key: m.key,
						label: m.label,
						value: data.totals[ m.key + '_formatted' ]
					} );
				} );
			} )
			: null;

		var children = [
			el( ImportStatus, {
				key: 'import',
				diagnostics: diagnostics,
				running: importing.running,
				progress: importing.progress
			} ),
			el(
				'div',
				{ key: 'filters', className: 'sbsbc-filters' },
				el( Filter, {
					label: __( 'Country', TEXTDOMAIN ),
					value: country,
					options: settings.countries || [],
					onChange: function ( value ) {
						setFilter( { sbsbcCountry: value } );
					}
				} ),
				el( Filter, {
					label: __( 'Year', TEXTDOMAIN ),
					value: String( year ),
					options: settings.years || [],
					onChange: function ( value ) {
						setFilter( { sbsbcYear: value } );
					}
				} ),
				el( StatusFilter, {
					value: statuses,
					onChange: function ( next ) {
						setFilter( { sbsbcStatuses: next.join( ',' ) } );
					}
				} )
			)
		];

		if ( data.error ) {
			children.push(
				el(
					'div',
					{ key: 'error', className: 'notice notice-error sbsbc-error' },
					el( 'p', null, data.error )
				)
			);
		}

		if ( summary ) {
			children.push( el( 'div', { key: 'summary' }, summary ) );
		}

		children.push(
			el( TableCard, {
				key: 'table',
				title: __( 'Sales by State', TEXTDOMAIN ),
				headers: headers,
				rows: rows,
				rowsPerPage: perPage,
				totalRows: sorted.length,
				isLoading: data.loading,
				query: query,
				onQueryChange: onQueryChange,
				showMenu: false
			} )
		);

		return el( 'div', { className: 'woocommerce-analytics__report sbsbc-report' }, children );
	}

	/**
	 * On a standalone admin page there is no router, so the report keeps the
	 * query in component state and mirrors it to the address bar itself.
	 *
	 * @return {Object} Element.
	 */
	function StandaloneReport() {
		var state = useState( readUrlQuery );
		var query = state[ 0 ];
		var setState = state[ 1 ];

		useEffect( function () {
			function onPop() {
				setState( readUrlQuery() );
			}

			window.addEventListener( 'popstate', onPop );

			return function () {
				window.removeEventListener( 'popstate', onPop );
			};
		}, [] );

		function setQuery( next ) {
			setState( function ( current ) {
				var merged = Object.assign( {}, current, next );

				writeUrlQuery( merged );

				return merged;
			} );
		}

		return el( SalesByStateReport, { query: query, setQuery: setQuery } );
	}

	/**
	 * Read the report's parameters out of the address bar.
	 *
	 * @return {Object} Query.
	 */
	function readUrlQuery() {
		var query = {};
		var keys = [ 'sbsbcCountry', 'sbsbcYear', 'sbsbcStatuses', 'orderby', 'order', 'paged', 'per_page' ];
		var search = window.location.search.replace( /^\?/, '' );
		var found = {};

		search.split( '&' ).forEach( function ( pair ) {
			if ( ! pair ) {
				return;
			}

			var parts = pair.split( '=' );

			found[ decodeURIComponent( parts[ 0 ] ) ] = decodeURIComponent( ( parts[ 1 ] || '' ).replace( /\+/g, ' ' ) );
		} );

		keys.forEach( function ( key ) {
			if ( found[ key ] ) {
				query[ key ] = found[ key ];
			}
		} );

		query.page = found.page || 'sbsbc-sales-by-state';

		return query;
	}

	/**
	 * Mirror the query to the address bar so a view can be shared or reloaded.
	 *
	 * @param {Object} query Query.
	 */
	function writeUrlQuery( query ) {
		var params = [];

		Object.keys( query ).forEach( function ( key ) {
			if ( '' !== query[ key ] && null !== query[ key ] && undefined !== query[ key ] ) {
				params.push( encodeURIComponent( key ) + '=' + encodeURIComponent( query[ key ] ) );
			}
		} );

		window.history.pushState( null, '', window.location.pathname + '?' + params.join( '&' ) );
	}

	/**
	 * Render the report into the standalone page's root element.
	 */
	function mountStandalone() {
		function render() {
			var node = document.getElementById( 'sbsbc-root' );

			if ( ! node ) {
				return;
			}

			if ( node.getAttribute( 'data-sbsbc-mounted' ) ) {
				return;
			}

			node.setAttribute( 'data-sbsbc-mounted', '1' );

			if ( wp.element.createRoot ) {
				wp.element.createRoot( node ).render( el( StandaloneReport ) );
				return;
			}

			wp.element.render( el( StandaloneReport ), node );
		}

		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', render );
			return;
		}

		render();
	}

	mountStandalone();
} )( window.wp, window.sbsbcConfig );
