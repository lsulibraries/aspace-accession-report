<?php

function search_records($search) {
  $search = clean_search_string($search);
  $pdo = get_connection();
  $where = get_where();
  $query = $pdo->prepare(get_query($where));
  if (FALSE == $query) {
    throw new InvalidArgumentException($search);
  }
  $query->execute(["%$search%", "%$search%"]);
  $results = $query->fetchAll();
  return truncateFields(interpolateBools($results));
}

function get_where($like = TRUE, $field = NULL) {
  if (!in_array($field, ['acc', 'mss', NULL])) {
    throw IllegalArgumentException("Illegal value [] specified for 'field'");
  }
  $operator = $like ? 'LIKE' : "=";
  $acc_clause = sprintf(<<<EOC
  concat(SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 2), '"', -1)," ", SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 4), '"', -1)) %s ?
EOC
  , $operator);
  $mss_clause = sprintf("ud.string_1 %s ?", $operator);

  if ($field == 'acc') {
    return sprintf($acc_clause, $operator);
  }
  elseif ($field == 'mss') {
    return sprintf($mss_clause, $operator);
  }
  else {
    return sprintf("%s OR %s", $mss_clause, $acc_clause);
  }
}

function get_record($id) {
  // $id = clean_search_string($id);
  $pdo = get_connection();
  $where = get_where(FALSE, 'acc');
  $query = $pdo->prepare(get_query($where));
  $query->execute([$id]);
  $results = $query->fetchAll();
  return truncateFields(interpolateBools($results));
}

function clean_search_string($search) {
  if (FALSE !== stripos($search, 'acc')) {
    $search = str_ireplace('-', ' ', str_ireplace('acc', '', $search));
  }
  return $search;
}

function interpolateBools($results) {
  $boolean_fields = ['Restrictions Apply', 'Access Restrictions', 'Use Restrictions'];
  foreach ($results as $id => $row) {
    foreach ($boolean_fields as $field) {
      $results[$id][$field] = $results[$id][$field] == 0 ? 'False' : 'True';
    }
  }
  return $results;
}

function truncateFields($results) {
  $long_fields = ['General Note', 'Content Description'];
  $word_quota = 64;
  foreach ($results as $id => $row) {
    foreach ($long_fields as $field) {
      $words = explode(' ', $results[$id][$field]);
      if (count($words) > $word_quota) {
        $display_words = array_slice($words, 0, $word_quota);
        array_push($display_words, '... [TRUNCATED]');
      }
      else {
        $display_words = $words;
      }

      $results[$id][$field] = implode(' ', $display_words);
    }
  }
  return $results;
}

function get_connection() {
  $config = parse_ini_file('config.ini');
  $host = $config['host'];
  $db   = $config['db_name'];
  $user = $config['user'];
  $pass = $config['pass'];
  $charset = 'utf8mb4';

  $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
  $options = [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
  ];
  try {
      $pdo = new PDO($dsn, $user, $pass, $options);
  } catch (\PDOException $e) {
      throw new \PDOException($e->getMessage(), (int)$e->getCode());
  }
  return $pdo;
}

function trim_query_param($query_param) {
    $parts = explode('-', $query_param);
    $trimmed = array_map('trim', $parts);
    return implode('-', $trimmed);
}

function get_query($where_clause) {
    $query = <<<EOQ

    select
      -- count(*)
             concat(SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 2), '"', -1)," ", SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 4), '"', -1)) as "Accession Identifier",
             acc.title "Collection Title",
             ud.string_2 "Location",
             (
               select
                 -- `expression`
                 CASE
                   when `expression` IS NULL AND end IS NOT NULL then CONCAT(begin, ' - ', end)
                   when `expression` IS NULL AND end IS NULL then begin
                   else `expression`
                 END
               from date
               where accession_id = acc.id
               LIMIT 1
             ) "Collection Dates",


             (
               GROUP_CONCAT(
               CONCAT_WS(' ',
                 CONCAT('(', (SELECT value from enumeration_value ev where ev.id = e.portion_id),')'),
           	     CONCAT(number, ' ', (SELECT value from enumeration_value ev where ev.id = e.extent_type_id)),
           	     CONCAT('[', CONCAT('Container Summary: ', container_summary), ']')
               )
               ORDER BY e.id
               SEPARATOR ',      ')
             ) 'Extent(s)',


             ud.date_1 "Date Received",
             acc.accession_date "Accession Date",
             ( SELECT value FROM enumeration_value where id = acc.acquisition_type_id) "Acquisition Type",
             acc.restrictions_apply "Restrictions Apply",
             acc.access_restrictions "Access Restrictions",
             acc.access_restrictions_note "Access Note",
             acc.use_restrictions "Use Restrictions",
             acc.use_restrictions_note "Use Note",
             acc.content_description "Content Description",
             acc.general_note "General Note",
             ud.string_1 "Mss Number",
             source.name,
             source.address_1,
             source.city,
             source.region,
             source.post_code,
             ud.real_1 "Price",
             concat('https://aspace.lib.lsu.edu/accessions/', acc.id) "ArchivesSpace URL"

    from
       accession acc left join user_defined ud on ud.accession_id = acc.id
       -- accession id 10280 has two extent entries, so the inclusion of this join adds another record to the total.
       -- To ensure counts, the columns provided by this join will be constructed with subqueries in the select list.
       -- uncomment the following line if
       left join extent e on e.accession_id = acc.id
       left join linked_agents_rlshp lar on lar.accession_id = acc.id

       left join (
         select *
         from date d
         where (
           select value
           from enumeration_value
           where id=d.date_type_id) = "bulk") bulk
         on bulk.accession_id = acc.id
       left join
         (
         select l.id, ac.name, ac.address_1, ac.city, ac.region, ac.post_code from agent_person ap join agent_contact ac on ap.id = ac.agent_person_id join linked_agents_rlshp l on l.agent_person_id = ap.id WHERE l.agent_person_id IS NOT NULL and l.role_id = 881
         UNION
         select l.id, ac.name, ac.address_1, ac.city, ac.region, ac.post_code from agent_software asw join agent_contact ac on asw.id = ac.agent_software_id join linked_agents_rlshp l on l.agent_software_id = asw.id WHERE l.agent_software_id IS NOT NULL and l.role_id = 881
         UNION
         select l.id, ac.name, ac.address_1, ac.city, ac.region, ac.post_code from agent_family af join agent_contact ac on af.id = ac.agent_family_id join linked_agents_rlshp l on af.id = l.agent_family_id WHERE l.agent_family_id IS NOT NULL and l.role_id = 881
         UNION
         select l.id, ac.name, ac.address_1, ac.city, ac.region, ac.post_code from agent_corporate_entity acorp join agent_contact ac on acorp.id = ac.agent_corporate_entity_id join linked_agents_rlshp l on acorp.id = l.agent_corporate_entity_id WHERE l.agent_corporate_entity_id IS NOT NULL and l.role_id = 881
       ) source on source.id = lar.id

where $where_clause
GROUP BY acc.identifier, acc.title, ud.string_2, acc.id, ud.date_1, ud.string_1, lar.agent_person_id, lar.agent_software_id, lar.agent_family_id, lar.agent_corporate_entity_id, ud.real_1, source.name, source.address_1, source.city, source.region, source.post_code

ORDER BY ud.string_1

EOQ;

  return $query;
}
