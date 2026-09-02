<?php
/**
 * Country and state labels for the report.
 *
 * @package SalesByStateReportForBigCommerce
 */

namespace SBSBC;

defined( 'ABSPATH' ) || exit;

/**
 * US, Canada, and UK subdivisions.
 *
 * BigCommerce V2 orders store a billing country and state. US and Canada
 * typically write the full name (California, Ontario). UK orders write
 * the county name into `state`, so those labels live here so zero-sales
 * rows still appear.
 */
class Regions {

	/**
	 * Countries the report always offers.
	 *
	 * @return array<string,string>
	 */
	public static function countries() {
		return array(
			'US' => __( 'United States', 'sales-by-state-report-for-bigcommerce' ),
			'CA' => __( 'Canada', 'sales-by-state-report-for-bigcommerce' ),
			'GB' => __( 'United Kingdom', 'sales-by-state-report-for-bigcommerce' ),
		);
	}

	/**
	 * Map a stored state name or code onto the catalog key.
	 *
	 * BigCommerce often stores "California" rather than "CA". UK county
	 * names are kept as names.
	 *
	 * @param string $country Country code.
	 * @param string $state   State code or name.
	 * @return string
	 */
	public static function normalize_state( $country, $state ) {
		$country = strtoupper( (string) $country );
		$state   = trim( (string) $state );

		if ( '' === $state ) {
			return '';
		}

		$catalog = self::states_for( $country );

		if ( ! $catalog ) {
			return substr( $state, 0, 50 );
		}

		if ( isset( $catalog[ $state ] ) ) {
			return $state;
		}

		$upper = strtoupper( $state );

		if ( isset( $catalog[ $upper ] ) ) {
			return $upper;
		}

		foreach ( $catalog as $code => $name ) {
			if ( 0 === strcasecmp( (string) $code, $state ) || 0 === strcasecmp( (string) $name, $state ) ) {
				return (string) $code;
			}
		}

		return substr( $state, 0, 50 );
	}

	/**
	 * State code => name for a country.
	 *
	 * @param string $country Country code.
	 * @return array<string,string>
	 */
	public static function states_for( $country ) {
		$country = strtoupper( (string) $country );

		if ( 'CA' === $country ) {
			return self::canada();
		}

		if ( 'GB' === $country ) {
			return self::united_kingdom();
		}

		if ( 'US' === $country ) {
			return self::united_states();
		}

		return array();
	}

	/**
	 * US states and DC.
	 *
	 * @return array<string,string>
	 */
	private static function united_states() {
		return array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);
	}

	/**
	 * Canadian provinces and territories.
	 *
	 * @return array<string,string>
	 */
	private static function canada() {
		return array(
			'AB' => 'Alberta',
			'BC' => 'British Columbia',
			'MB' => 'Manitoba',
			'NB' => 'New Brunswick',
			'NL' => 'Newfoundland and Labrador',
			'NT' => 'Northwest Territories',
			'NS' => 'Nova Scotia',
			'NU' => 'Nunavut',
			'ON' => 'Ontario',
			'PE' => 'Prince Edward Island',
			'QC' => 'Quebec',
			'SK' => 'Saskatchewan',
			'YT' => 'Yukon',
		);
	}

	/**
	 * UK counties as BigCommerce stores them in the state field.
	 *
	 * Keys are county names, not ENG/SCT/WLS/NIR codes, because UK orders
	 * write the county name into `state`.
	 *
	 * @return array<string,string>
	 */
	private static function united_kingdom() {
		$names = array(
			'London',
			'Greater London',
			'Greater Manchester',
			'West Midlands',
			'West Yorkshire',
			'South Yorkshire',
			'Merseyside',
			'Tyne and Wear',
			'Kent',
			'Essex',
			'Hampshire',
			'Surrey',
			'Lancashire',
			'Hertfordshire',
			'Norfolk',
			'Suffolk',
			'Devon',
			'Cornwall',
			'Somerset',
			'Dorset',
			'Wiltshire',
			'Gloucestershire',
			'Oxfordshire',
			'Buckinghamshire',
			'Berkshire',
			'Bedfordshire',
			'Cambridgeshire',
			'Northamptonshire',
			'Leicestershire',
			'Nottinghamshire',
			'Derbyshire',
			'Staffordshire',
			'Warwickshire',
			'Worcestershire',
			'Herefordshire',
			'Shropshire',
			'Cheshire',
			'Cumbria',
			'Northumberland',
			'Durham',
			'Lincolnshire',
			'North Yorkshire',
			'East Riding of Yorkshire',
			'East Sussex',
			'West Sussex',
			'Isle of Wight',
			'Rutland',
			'Bristol',
			'Midlothian',
			'West Lothian',
			'East Lothian',
			'Fife',
			'Lanarkshire',
			'Aberdeenshire',
			'Highland',
			'Glasgow',
			'Edinburgh',
			'South Glamorgan',
			'Mid Glamorgan',
			'West Glamorgan',
			'Gwent',
			'Gwynedd',
			'Dyfed',
			'Clwyd',
			'Powys',
			'Antrim',
			'Armagh',
			'Down',
			'Fermanagh',
			'Londonderry',
			'Tyrone',
		);

		$out = array();

		foreach ( $names as $name ) {
			$out[ $name ] = $name;
		}

		return $out;
	}
}
