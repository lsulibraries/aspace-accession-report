<?php

function search_records($search) {
  $pdo = get_connection();
  $query = $pdo->prepare(get_query('ud.string_1 LIKE ?'));
  if (FALSE == $query) {
    throw new InvalidArgumentException($search);
  }
  $query->execute(["%$search%", "%$search%"]);
  $results = $query->fetchAll();
  return truncateFields(interpolateBools($results));
}


function get_record($id) {
  $pdo = get_connection();
  $query = $pdo->prepare(get_query('ud.string_1 = ?'));
  $query->execute([$id, $id]);
  $results = $query->fetchAll();
  return truncateFields(interpolateBools($results));
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
         concat(SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 2), '"', -1)," ", SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 4), '"', -1)) as "Accession Identifier",
         acc.title "Collection Title",
         ud.string_2 "Location",
         d.`expression` "Collection Dates",

         CONCAT(e.number, ' ', (select value from enumeration_value where id = e.extent_type_id)) Extent,
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
         creators.Name Creator,
         creators.Address,
         creators.City,
         creators.Region,
         creators.post_code 'Post Code',
         ud.real_1 "Price",
         concat('https://aspace.lib.lsu.edu/accessions/', acc.id) "ArchivesSpace URL"


from
   accession acc inner join user_defined ud on ud.accession_id = acc.id
   left join extent e on e.accession_id = acc.id
/*   left join linked_agents_rlshp lar on lar.accession_id = acc.id
   left join name_person np on lar.agent_person_id = np.id
   left join agent_contact ac on lar.agent_person_id = ac.agent_person_id */
   left join date d on d.accession_id = acc.id
   left join (select * from date d
    where (select value from enumeration_value where id=d.date_type_id) = "bulk") bulk on bulk.accession_id = acc.id
   left join (select
     a.id,
     l.agent_person_id,
     n.sort_name Name,
     ac.address_1 Address,
     ac.city City,
     ac.region Region,
     ac.post_code,
     l.role_id,
     (select value from enumeration_value where id=l.role_id) role,
     a.title,
     a.identifier
   from accession a
     join linked_agents_rlshp l on a.id = l.accession_id
     join name_person n on n.agent_person_id = l.agent_person_id
     left join agent_contact ac on ac.agent_person_id = l.agent_person_id
   where l.role_id = 880) creators ON creators.id = acc.id
where ((select value from enumeration_value where id=d.date_type_id) = "inclusive"
    or (select value from enumeration_value where id=d.date_type_id) = "single")
    and
    (
      concat(SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 2), '"', -1)," ", SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 4), '"', -1)) like ?
    OR
    $where_clause
    )

ORDER BY ud.string_1

EOQ;

  return $query;
}
