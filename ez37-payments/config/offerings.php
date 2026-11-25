<?php
/**
 * Offering catalogue — edited by a developer (decision D8).
 *
 * Each key is the offering identifier used on the website, e.g.
 * /checkout/?offering=leadership-coaching. The browser only ever sends this
 * key; price, currency and Stripe Price come from here.
 *
 * Fields:
 * - name        Shown to the customer on the checkout page and in emails.
 * - active      false hides the offering and refuses checkout.
 * - currency    Lowercase ISO code. Must match the Stripe Price.
 * - amount      Price in the smallest unit (cents). Must match the Stripe Price.
 * - price_ids   Stripe Price ID per mode. Price IDs are not secret.
 *               An empty value disables the offering in that mode.
 * - tax         'none' only, until tax is confirmed (decision D7).
 * - fulfilment  What the customer receives (implemented in Phase 6).
 *
 * TEST PRICES ONLY. Real prices and live Price IDs are added before go-live.
 *
 * @package EZ37\Payments
 */

defined('ABSPATH') || exit;

return array(
	'pccp' => array(
		'name' => 'Professional Coach Certification Programme',
		'active' => true,
		'currency' => 'usd',
		// $2,699.00 USD (amount is in cents), as shown on the previous EZ Coaching product page.
		'amount' => 269900,
		'price_ids' => array(
			// Create a one-time $2,699.00 USD Price in the Stripe sandbox, paste its ID here.
			// Until then this offering cannot be bought in test mode.
			'test' => 'price_1UfHUzIHxBQcfaUGKjjsN3bP',
			// Live Price ID from the client's Stripe account, added before go-live.
			'live' => '',
		),
		'tax' => 'none',
		'agreement' => 'pccp-enrolment',
		// Collected before payment and stored with the order. Edit labels/options here.
		// Collected before payment and stored with the order (from the client's Google enrolment form).
		'enrolment' => array(
			array(
				'key' => 'first_name',
				'label' => 'First name',
				'required' => true,
				'autocomplete' => 'given-name',
				'half' => true,
			),
			array(
				'key' => 'last_name',
				'label' => 'Last name',
				'required' => true,
				'autocomplete' => 'family-name',
				'half' => true,
			),
			array(
				'key' => 'organisation',
				'label' => 'Organisation',
				'required' => true,
				'autocomplete' => 'organization',
				'half' => true,
			),
			array(
				'key' => 'job_title',
				'label' => 'Role / job title',
				'required' => true,
				'autocomplete' => 'organization-title',
				'half' => true,
			),
			array(
				'key' => 'phone',
				'label' => 'Contact number',
				'type' => 'tel',
				'required' => true,
				'autocomplete' => 'tel',
				'half' => true,
			),
			array(
				'key' => 'country',
				'label' => 'Country',
				'required' => true,
				'autocomplete' => 'country-name',
				'half' => true,
			),
			array(
				'key' => 'gender',
				'label' => 'Gender',
				'type' => 'select',
				'required' => true,
				'options' => array('Male', 'Female'),
				'half' => true,
			),
			array(
				'key' => 'sponsor',
				'label' => 'Who is paying for the programme?',
				'type' => 'select',
				'required' => true,
				'options' => array('Self', 'Organisation', 'Other'),
				'half' => true,
			),
			// Full payment goes to Stripe. Any other answer is NOT charged: the form is stored and the team sends an invoice.
			array(
				'key' => 'payment_option',
				'label' => 'Payment option',
				'type' => 'select',
				'required' => true,
				'options' => array('Full payment', '2 instalments', '3 instalments', '4 instalments', '5 instalments', '6 instalments', '7 instalments', 'Other'),
				'half' => true,
			),
			array(
				'key' => 'payment_start',
				'label' => 'When would you like to make your first payment? (Month/Year)',
				'half' => true,
			),
			array(
				'key' => 'invoice_to',
				'label' => 'Who should the invoice be issued to? (name or organisation, email and contact)',
				'type' => 'textarea',
				'max' => 500,
			),
			array(
				'key' => 'objective',
				'label' => 'What is the objective or purpose behind your decision to enrol in this course?',
				'type' => 'textarea',
				'required' => true,
				'max' => 1000,
			),
			array(
				'key' => 'heard_about',
				'label' => 'How did you hear about us?',
				'type' => 'select',
				'required' => true,
				'options' => array('LinkedIn', 'Facebook', 'Email', 'WhatsApp', 'Search Engine', 'Instagram', 'Referral', 'Webinar', 'Other'),
				'half' => true,
			),
			array(
				'key' => 'referrer',
				'label' => 'Who referred you? (full name, if applicable)',
				'half' => true,
			),
			array(
				'key' => 'comments',
				'label' => 'Anything else you would like us to know?',
				'type' => 'textarea',
				'max' => 1000,
			),
		),
		'fulfilment' => array(
			'handler' => 'resource_access',
			'resources' => array('pccp-programme-brochure'),
		),
	),
	'accp' => array(
		'name' => 'Advanced Coach Certification Programme',
		'active' => true,
		'currency' => 'usd',
		// $2,799.00 USD (amount is in cents), as shown on the previous EZ Coaching enrolment form (Level 2).
		'amount' => 279900,
		'price_ids' => array(
			// Create a one-time $2,799.00 USD Price in the Stripe sandbox, paste its ID here.
			// Until then this offering cannot be bought in test mode.
			'test' => 'price_1UfKeyIHxBQcfaUGUKV7xryC',
			// Live Price ID from the client's Stripe account, added before go-live.
			'live' => '',
		),
		'tax' => 'none',
		'agreement' => 'accp-enrolment',
		// Collected before payment and stored with the order. Edit labels/options here.
		// Collected before payment and stored with the order (from the client's Google enrolment form).
		'enrolment' => array(
			array(
				'key' => 'first_name',
				'label' => 'First name',
				'required' => true,
				'autocomplete' => 'given-name',
				'half' => true,
			),
			array(
				'key' => 'last_name',
				'label' => 'Last name',
				'required' => true,
				'autocomplete' => 'family-name',
				'half' => true,
			),
			array(
				'key' => 'organisation',
				'label' => 'Organisation',
				'required' => true,
				'autocomplete' => 'organization',
				'half' => true,
			),
			array(
				'key' => 'job_title',
				'label' => 'Role / job title',
				'required' => true,
				'autocomplete' => 'organization-title',
				'half' => true,
			),
			array(
				'key' => 'phone',
				'label' => 'Contact number',
				'type' => 'tel',
				'required' => true,
				'autocomplete' => 'tel',
				'half' => true,
			),
			array(
				'key' => 'country',
				'label' => 'Country',
				'required' => true,
				'autocomplete' => 'country-name',
				'half' => true,
			),
			array(
				'key' => 'gender',
				'label' => 'Gender',
				'type' => 'select',
				'required' => true,
				'options' => array('Male', 'Female'),
				'half' => true,
			),
			array(
				'key' => 'sponsor',
				'label' => 'Who is paying for the programme?',
				'type' => 'select',
				'required' => true,
				'options' => array('Self', 'Organisation', 'Other'),
				'half' => true,
			),
			// Full payment goes to Stripe. Any other answer is NOT charged: the form is stored and the team sends an invoice.
			array(
				'key' => 'payment_option',
				'label' => 'Payment option',
				'type' => 'select',
				'required' => true,
				'options' => array('Full payment', '2 instalments', '3 instalments', '4 instalments', '5 instalments', '6 instalments', '7 instalments', 'Other'),
				'half' => true,
			),
			array(
				'key' => 'payment_start',
				'label' => 'When would you like to make your first payment? (Month/Year)',
				'half' => true,
			),
			array(
				'key' => 'invoice_to',
				'label' => 'Who should the invoice be issued to? (name or organisation, email and contact)',
				'type' => 'textarea',
				'max' => 500,
			),
			array(
				'key' => 'objective',
				'label' => 'What is the objective or purpose behind your decision to enrol in this course?',
				'type' => 'textarea',
				'required' => true,
				'max' => 1000,
			),
			array(
				'key' => 'heard_about',
				'label' => 'How did you hear about us?',
				'type' => 'select',
				'required' => true,
				'options' => array('LinkedIn', 'Facebook', 'Email', 'WhatsApp', 'Search Engine', 'Instagram', 'Referral', 'Webinar', 'Other'),
				'half' => true,
			),
			array(
				'key' => 'referrer',
				'label' => 'Who referred you? (full name, if applicable)',
				'half' => true,
			),
			array(
				'key' => 'comments',
				'label' => 'Anything else you would like us to know?',
				'type' => 'textarea',
				'max' => 1000,
			),
		),
		'fulfilment' => array(
			'handler' => 'resource_access',
			'resources' => array('accp-programme-brochure'),
		),
	),
	'coaching-skills' => array(
		'name' => 'Coaching Skills Training for Managers and Leaders',
		'active' => true,
		'currency' => 'usd',
		// $500.00 USD (amount is in cents).
		'amount' => 50000,
		'price_ids' => array(
			// Create a one-time $500.00 USD Price in the Stripe sandbox, paste its ID here.
			'test' => 'price_1UfLAtIHxBQcfaUGUAMEhfx5',
			// Live Price ID from the client's Stripe account, added before go-live.
			'live' => '',
		),
		'tax' => 'none',
		// Full payment only: no agreement and no instalment/invoice option on this course.
		'enrolment' => array(
			array(
				'key' => 'first_name',
				'label' => 'First name',
				'required' => true,
				'autocomplete' => 'given-name',
				'half' => true,
			),
			array(
				'key' => 'last_name',
				'label' => 'Last name',
				'required' => true,
				'autocomplete' => 'family-name',
				'half' => true,
			),
			array(
				'key' => 'organisation',
				'label' => 'Organisation',
				'required' => true,
				'autocomplete' => 'organization',
				'half' => true,
			),
			array(
				'key' => 'job_title',
				'label' => 'Role / job title',
				'required' => true,
				'autocomplete' => 'organization-title',
				'half' => true,
			),
			array(
				'key' => 'phone',
				'label' => 'Contact number',
				'type' => 'tel',
				'required' => true,
				'autocomplete' => 'tel',
				'half' => true,
			),
			array(
				'key' => 'country',
				'label' => 'Country',
				'required' => true,
				'autocomplete' => 'country-name',
				'half' => true,
			),
			array(
				'key' => 'objective',
				'label' => 'What is the objective or purpose behind your decision to enrol in this course?',
				'type' => 'textarea',
				'required' => true,
				'max' => 1000,
			),
			array(
				'key' => 'heard_about',
				'label' => 'How did you hear about us?',
				'type' => 'select',
				'required' => true,
				'options' => array('LinkedIn', 'Facebook', 'Email', 'WhatsApp', 'Search Engine', 'Instagram', 'Referral', 'Webinar', 'Other'),
				'half' => true,
			),
			array(
				'key' => 'referrer',
				'label' => 'Who referred you? (full name, if applicable)',
				'half' => true,
			),
			array(
				'key' => 'comments',
				'label' => 'Anything else you would like us to know?',
				'type' => 'textarea',
				'max' => 1000,
			),
		),
		'fulfilment' => array(
			'handler' => 'resource_access',
			'resources' => array('coaching-skills-brochure'),
		),
	),
	'amcmp' => array(
		'name' => 'Advanced Mentor Coaching Mastery Programme',
		'active' => true,
		'currency' => 'usd',
		// Early bird $2,300.00 USD (standard fee $2,499) (amount is in cents).
		'amount' => 230000,
		'price_ids' => array(
			// Create a one-time USD Price for this amount in the Stripe sandbox, paste its ID here.
			'test' => 'price_1UfZr6IHxBQcfaUGc3vkilm9',
			// Live Price ID from the client's Stripe account, added before go-live.
			'live' => '',
		),
		'tax' => 'none',
		// Full payment only: no agreement and no instalment/invoice option.
		'enrolment' => array(
			array(
				'key' => 'first_name',
				'label' => 'First name',
				'required' => true,
				'autocomplete' => 'given-name',
				'half' => true,
			),
			array(
				'key' => 'last_name',
				'label' => 'Last name',
				'required' => true,
				'autocomplete' => 'family-name',
				'half' => true,
			),
			array(
				'key' => 'phone',
				'label' => 'Contact number (WhatsApp / WeChat ID / Red Note / Mobile)',
				'type' => 'tel',
				'required' => true,
				'autocomplete' => 'tel',
				'half' => true,
			),
			array(
				'key' => 'country',
				'label' => 'Country and time zone',
				'required' => true,
				'half' => true,
			),
			array(
				'key' => 'icf_credential',
				'label' => 'What is your current active ICF credential level?',
				'type' => 'select',
				'required' => true,
				'options' => array('Renewed ACC (Associate Certified Coach), with at least 1 full renewal cycle completed', 'PCC (Professional Certified Coach)', 'MCC (Master Certified Coach)', 'Initial ACC cycle', 'None of the above'),
			),
			array(
				'key' => 'mentor_experience',
				'label' => 'Mentor coaching experience',
				'type' => 'select',
				'required' => true,
				'options' => array('None', 'Some', 'Experienced'),
				'half' => true,
			),
			array(
				'key' => 'learn',
				'label' => 'What do you hope to learn from our program?',
				'type' => 'textarea',
				'required' => true,
				'max' => 1000,
			),
			array(
				'key' => 'heard_about',
				'label' => 'How did you hear about this program?',
				'type' => 'select',
				'required' => true,
				'options' => array('LinkedIn', 'Facebook', 'Email', 'WhatsApp', 'Search Engine', 'Instagram', 'Referral', 'Webinar', 'We Chat', 'Red Note', 'ICF website/directory'),
			),
			array(
				'key' => 'comments',
				'label' => 'Do you have any additional comments or specific information you would like our admissions team to know?',
				'type' => 'textarea',
				'max' => 1000,
			),
		),
		'fulfilment' => array(
			'handler' => 'resource_access',
			'resources' => array('amcmp-brochure'),
		),
	),
	'mcs' => array(
		'name' => 'Mentor Coach Specialization (MCS) Training',
		'active' => true,
		'currency' => 'usd',
		// Early bird $490.00 USD, valid until 31 December 2026 (standard fee $525) (amount is in cents).
		'amount' => 49000,
		'price_ids' => array(
			// Create a one-time USD Price for this amount in the Stripe sandbox, paste its ID here.
			'test' => 'price_1UfZt8IHxBQcfaUGtxsy4v4x',
			// Live Price ID from the client's Stripe account, added before go-live.
			'live' => '',
		),
		'tax' => 'none',
		// Full payment only: no agreement and no instalment/invoice option.
		'enrolment' => array(
			array(
				'key' => 'first_name',
				'label' => 'First name',
				'required' => true,
				'autocomplete' => 'given-name',
				'half' => true,
			),
			array(
				'key' => 'last_name',
				'label' => 'Last name',
				'required' => true,
				'autocomplete' => 'family-name',
				'half' => true,
			),
			array(
				'key' => 'phone',
				'label' => 'Contact number (WhatsApp / WeChat ID / Red Note / Mobile)',
				'type' => 'tel',
				'required' => true,
				'autocomplete' => 'tel',
				'half' => true,
			),
			array(
				'key' => 'country',
				'label' => 'Country and time zone',
				'required' => true,
				'half' => true,
			),
			array(
				'key' => 'icf_credential',
				'label' => 'What is your current active ICF credential level?',
				'type' => 'select',
				'required' => true,
				'options' => array('Renewed ACC (Associate Certified Coach), with at least 1 full renewal cycle completed', 'PCC (Professional Certified Coach)', 'MCC (Master Certified Coach)', 'Initial ACC cycle', 'None of the above'),
			),
			array(
				'key' => 'learn',
				'label' => 'What do you hope to learn from our program?',
				'type' => 'textarea',
				'required' => true,
				'max' => 1000,
			),
			array(
				'key' => 'heard_about',
				'label' => 'How did you hear about this program?',
				'type' => 'select',
				'required' => true,
				'options' => array('LinkedIn', 'Facebook', 'Email', 'WhatsApp', 'Search Engine', 'Instagram', 'Referral', 'Webinar', 'We Chat', 'Red Note', 'Other'),
			),
			array(
				'key' => 'comments',
				'label' => 'Do you have any additional comments or specific information you would like our admissions team to know?',
				'type' => 'textarea',
				'max' => 1000,
			),
		),
		'fulfilment' => array(
			'handler' => 'resource_access',
			'resources' => array('mcs-brochure'),
		),
	),
	'peer-to-peer' => array(
		'name' => 'EZ Peer to Peer Coaching',
		'active' => true,
		'currency' => 'usd',
		// Free registration for EZ Coaching alumni: nothing is charged and Stripe is never called.
		'free' => true,
		'amount' => 0,
		'price_ids' => array(
			'test' => '',
			'live' => '',
		),
		'tax' => 'none',
		'agreement' => 'peer-to-peer-agreement',
		'enrolment' => array(
			array(
				'key' => 'first_name',
				'label' => 'First name',
				'required' => true,
				'autocomplete' => 'given-name',
				'half' => true,
			),
			array(
				'key' => 'last_name',
				'label' => 'Last name',
				'required' => true,
				'autocomplete' => 'family-name',
				'half' => true,
			),
			array(
				'key' => 'phone',
				'label' => 'WhatsApp number (international format)',
				'type' => 'tel',
				'required' => true,
				'autocomplete' => 'tel',
				'half' => true,
			),
			array(
				'key' => 'country',
				'label' => 'Country',
				'autocomplete' => 'country-name',
				'half' => true,
			),
			array(
				'key' => 'city',
				'label' => 'City',
				'required' => true,
				'half' => true,
			),
			array(
				'key' => 'timezone',
				'label' => 'Time zone',
				'required' => true,
				'half' => true,
			),
			array(
				'key' => 'hours_so_far',
				'label' => 'How many coaching hours have you accumulated so far?',
				'type' => 'select',
				'required' => true,
				'options' => array('Less than 100 Hours', 'Between 100 to 500 Hours', 'Between 500 to 2,500 Hours'),
				'half' => true,
			),
			array(
				'key' => 'hours_intended',
				'label' => 'How many coaching hours do you intend to acquire through this programme?',
				'type' => 'select',
				'required' => true,
				'options' => array('Less than 10 Hours', 'Between 10 to 100 Hours', 'More than 100 Hours'),
				'half' => true,
			),
		),
		'fulfilment' => array(),
	),
	'leadership-coaching' => array(
		'name' => 'Leadership Coaching',
		'active' => true,
		'currency' => 'usd',
		'amount' => 100,
		// TEST price: $1.00 (amount is in cents). Real price before go-live.
		'price_ids' => array(
			// Sandbox Price ID. Must belong to the same Stripe sandbox as the test secret key.
			'test' => 'price_1UbGnnIHxBQcfaUGoWhfpIHT',
			// Live Price ID from the client's Stripe account, added before go-live.
			'live' => '',
		),
		'tax' => 'none',
		'fulfilment' => array(
			'handler' => 'resource_access',
			'resources' => array('leadership-coaching-brochure'),
		),
	),
);
