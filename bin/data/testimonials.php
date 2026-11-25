<?php
/**
 * Testimonials (PCCP) grouped by industry. Shared by testimonials-local.php (list + interview pages)
 * and header-footer-local.php (so each interview page gets the new header and footer).
 *
 * To add a person: add one line to the right category. Photo = design/testimonials/tp-<slug>.png (square, any size);
 * until it exists an initials circle is shown. 'qa' = list of array( question, answer ); empty = placeholder interview.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ez37_testi_cats = array(
	'finance'     => 'Finance',
	'fintech'     => 'Fintech',
	'fmcg'        => 'FMCG',
	'energy'      => 'Energy',
	'consultancy' => 'Consultancy',
);

/* slug => array( name, credential, role, organisation, category ) */
$ez37_testi_people = array(
	'nafisah-muhammad-buba' => array( 'Nafisah Muhammad Buba', 'ACC', 'Head Human Capital Management and Administrative Services', 'NIRSAL Connect', 'finance' ),
	'oyiza-salu'            => array( 'Oyiza Salu', '', 'Chief Talent & Culture Officer', 'Polaris Bank Ltd', 'finance' ),
	'ajuma-madojemu'        => array( 'Ajuma Madojemu', '', 'Retired Director', 'Central Bank of Nigeria with Coaching Expertise', 'finance' ),
	'ololade-ogungbenro'    => array( 'Ololade Ogungbenro', '', 'Divisional Head People, Brands and Culture', 'Wema Bank Plc', 'finance' ),
	'abimbola-lawson'       => array( 'Abimbola Lawson', '', 'General Counsel / Board Advisor', 'Allianz Nigeria Insurance Ltd', 'finance' ),
	'omome-osakue'          => array( 'Omome Osakue', '', 'Finance Director, Heineken Haiti', 'Brasserie Nationale d&rsquo;Haiti S.A.', 'finance' ),

	'adenike-emiloju'       => array( 'Adenike Emiloju', '', 'Director Treasury', 'IHS Towers', 'fintech' ),
	'elizabeth-okonji'      => array( 'Elizabeth Okonji', 'PCC', 'Chief People Officer', 'SeamlessHR', 'fintech' ),
	'yewande-jinadu'        => array( 'Yewande Jinadu', 'ACC', 'Head, People & Culture', 'Traction Apps', 'fintech' ),
	'chidera-dimude'        => array( 'Chidera Dimude', 'ACC', 'Global Head of Performance & Talent Management', 'Wise', 'fintech' ),
	'omotola-adedapo'       => array( 'Dr Omotola Adedapo', '', 'Head of Human Resources', 'Sterling Financial Holdings Company', 'fintech' ),

	'adewale-arikawe'       => array( 'Adewale Arikawe', '', 'Managing Director, Sub Sahara Africa', 'Royal Friesland Campina Netherlands', 'fmcg' ),
	'marilyn-maduka'        => array( 'Marilyn Maduka', '', 'Sustainability Director, Africa', 'Anheuser-Busch InBev', 'fmcg' ),
	'tayo-ilori'            => array( 'Tayo Ilori', 'ACC', 'Director Supply Chain Operations', 'Honeywell Flour Mills Plc', 'fmcg' ),
	'grace-omo-lamai'       => array( 'Grace Omo-Lamai', '', 'Human Resources Director', 'Nigerian Breweries Plc', 'fmcg' ),
	'julia-esezobor'        => array( 'Julia Esezobor', 'PCC', 'People and Culture Director', 'Coca-Cola HBC', 'fmcg' ),
	'philomena-aneke'       => array( 'Philomena Aneke', '', 'Digital and Technology Director', 'Nigerian Breweries Plc', 'fmcg' ),
	'stanley-eluwa'         => array( 'Stanley Eluwa', '', 'Human Resources Director', 'Promasidor', 'fmcg' ),

	'adeola-kagho'          => array( 'Adeola Kagho', '', 'Country Head of HR, Nigeria', 'ENGIE Energy Access (Africa)', 'energy' ),
	'adesua-adewole'        => array( 'Adesua Adewole', '', 'General Manager (Human Resources)', 'Total Nigeria', 'energy' ),
	'margaret-ighalo-larbi' => array( 'Margaret Ighalo Larbi', '', 'Field HR Manager', 'ExxonMobil', 'energy' ),
	'stephanie-emodi'       => array( 'Stephanie Emodi', '', 'Human Capital and Admin Manager', 'BW Offshore Nigeria Ltd', 'energy' ),
	'francesca-enase'       => array( 'Francesca Enase', '', 'Senior Human Resources Business Partner', 'Savannah Energy Plc', 'energy' ),

	'seyi-onasanya'         => array( 'Seyi Onasanya', '', 'MD / CEO', 'SNEVA Global Associates', 'consultancy' ),
	'david-barnard'         => array( 'David Barnard', 'ACC', 'Director', 'Pracsys Advisory Limited', 'consultancy' ),
	'idotenyin-isaac'       => array( 'Idotenyin Isaac', 'ACC', 'Managing Partner', 'GEORGID Consulting', 'consultancy' ),
	'adepeju-adegoke'       => array( 'Adepeju Adegoke', '', 'Managing Partner', 'NeShama Concepts', 'consultancy' ),
);

/* Optional links per slug: 'linkedin' => profile URL, 'youtube' => video id (shown on the interview page). */
$ez37_testi_links = array(
	'nafisah-muhammad-buba' => array( 'linkedin' => 'https://www.linkedin.com/in/nafisah-muhammad-buba/' ),
	'oyiza-salu'            => array( 'linkedin' => 'https://www.linkedin.com/in/oyizasalu/' ),
	'ajuma-madojemu'        => array( 'linkedin' => 'https://www.linkedin.com/in/ajuma-madojemu-3b290727/', 'youtube' => 'C_-b7BbOZ5w' ),
	'ololade-ogungbenro'    => array( 'linkedin' => 'https://www.linkedin.com/in/ololade-ogungbenro-mcipd-3702a528/' ),
	'abimbola-lawson'       => array( 'linkedin' => 'http://linkedin.com/in/abimbola-alabi-b2217018' ),
	'omome-osakue'          => array( 'linkedin' => 'https://www.linkedin.com/in/omome-osakue-cfa-47b796b3/' ),
);

/* People with no interview: no detail page is built and their card links straight to LinkedIn. */
$ez37_testi_nopage = array( 'omome-osakue' );

/* Interview content per slug, exactly as supplied by the client (do not edit wording): array( array( 'Question?', 'Answer.' ), ... ). Empty = placeholder interview. */
$ez37_testi_qa = array(
	'nafisah-muhammad-buba' => array(
		array( 'Please tell us about your professional background and your current or most recent role.', 'Over 20 years experience in HR, Relationship Management, Customer Services, Administrative Services and Compliance; Most recent role – Head, HR &amp; Administrative Services Department' ),
		array( 'What were your reasons for enrolling in the Fundamentals of Coaching and Essentials of Coaching Course?', 'Personal Growth and Capacity development of young professionals via my personal coaching practice' ),
		array( 'How do you plan to utilise your new skills as a Certified Coach?', 'I plan to set up a Coaching Practice' ),
		array( 'What was your experience in this journey with EZ37 Solutions Ltd, the approved provider of your coach training programme?', 'Absolutely AWESOME!!!.' ),
	),
	'oyiza-salu' => array(
		array( 'Please tell us about your professional background and your current or most recent role.', 'Group Head, HR at GTB (Guaranty Trust Bank Limited)' ),
		array( 'What were your reasons for enrolling in the Fundamentals of Coaching and Essentials of Coaching Course?', 'Personal / professional growth' ),
		array( 'How do you plan to utilise your new skills as a Certified Coach?', 'Internal coach' ),
		array( 'What was your experience in this journey with EZ37 Solutions Ltd, the approved provider of your coach training programme?', 'Transformational.' ),
	),
	'ajuma-madojemu' => array(
		array( 'Please tell us about your professional background and your current or most recent role.', '• Assistant Director, currently Branch Controller with the Central Bank of Nigeria, Osogbo Branch<br>• Fellow, Institute of Strategic Management<br>• Pioneer and two-term Chairperson of the Oyo State Chapter<br>• Senior Member, Chartered Institute of Loan &amp; Risk Management<br>• Certified Health &amp; Wellness Coach (Inst. Of Integrative Nutrition)<br>• Member, Int’l Assoc. of Health Coaches' ),
		array( 'What was your experience in this journey with EZ37 Solutions Ltd, the training provider of the Professional Coach Certification Programme (PCCP) which is accredited by the International Coaching Federation (ICF)?', 'It was a life-changing experience for me. Great facilitators and excellent opportunities to network and practice coaching skills. Opened my eyes to possibilities.' ),
		array( 'What were your reasons for enrolling in the Fundamentals of Coaching and Essentials of Coaching Course?', 'I had already completed a program in health coaching which emphasized nutrition and needed to enhance my coaching skills.' ),
		array( 'How do you plan to utilise your new skills as a Certified Coach?', 'I plan to work with and help middle aged, mid-career &amp; pre-retirement people transit seamlessly into life post-retirement..' ),
	),
	'ololade-ogungbenro' => array(
		array( 'Please tell us about your professional background and your current or most recent role.', '– Divisional Head – People, Culture and Brands.<br>– 25 years in Human Resources in Manufacturing and Financial Services' ),
		array( 'What were your reasons for enrolling in the Fundamentals of Coaching and Essentials of Coaching Course?', 'To become a professional coach' ),
		array( 'How do you plan to utilise your new skills as a Certified Coach?', 'Take up professional coaching but on the job and as a private practice.' ),
		array( 'What was your experience in this journey with EZ37 Solutions Ltd, the approved provider of your coach training programme?', 'It was very educative and empowering.' ),
	),
	'abimbola-lawson' => array(
		array( 'Please tell us about your professional background and your current or most recent role.', 'Commenced my career as a lawyer in AEC Legal, thereafter moved to the insurance sector as in house counsel. I am now Chief Operating Officer for Allianz Nigeria' ),
		array( 'What were your reasons for enrolling in the Fundamentals of Coaching and Essentials of Coaching Course?', 'To broaden my HR skills' ),
		array( 'How do you plan to utilise your new skills as a Certified Coach?', 'To introduce the coaching culture in my company and also build my own coaching business' ),
		array( 'What was your experience in this journey with EZ37 Solutions Ltd, the approved provider of your coach training programme?', 'The training was simplifed and the use of the LMS was very helpful. The training team was also professional but friendly.' ),
	),
);
