<?php

function search_records($search) {
  $search = validate_query_param($search);
  $pdo = get_connection();
  $query = $pdo->prepare(get_query('and ud.string_1 LIKE ?'));
  $query->execute(["%$search%"]);
  return $query->fetchAll();
}


function get_record($id) {
  $id = validate_query_param($id);
  $pdo = get_connection();
  $query = $pdo->prepare(get_query('and ud.string_1 = ?'));
  $query->execute([$id]);
  return $query->fetchAll();
}

function get_connection() {
  $config = parse_ini_file('config.ini');
  $host = '127.0.0.1';
  $db   = 'archivesspace';
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

function validate_query_param($query_param) {
  foreach (explode('-', $query_param) as $chunk) { 
    if (!ctype_alnum($chunk)) {
      throw new Exception("expected alpha-numeric (optionally hyphen-delimited) argument, got '$query_param'.  ");
    }
  }
  return $query_param;
}

function get_query($where_clause) {
    
    $query = <<<EOQ

select
         ud.string_1 "Mss Number",
         acc.title Title,
         acc.content_description "Content Description",
         acc.general_note "General Note",
         acc.access_restrictions_note "Access Restrictions Note",
         acc.use_restrictions_note "Use Restrictions Note",
         acc.accession_date "Accession Date",
         ( SELECT value FROM enumeration_value where id = acc.acquisition_type_id) "Acquisition Type",
         ( SELECT value FROM enumeration_value where id = acc.resource_type_id) "Resource Type",
         acc.restrictions_apply "Restrictions Apply",
         acc.publish "Publish",
         acc.access_restrictions "Access Restrictions",
         acc.use_restrictions "Use Restrictions",
         ud.string_2 "Location",
         ud.string_3 "SIRSI Number",
         ud.date_1 "Date Received",
         ud.real_1 "Price",
         e.number Number,
         (select value from enumeration_value where id = e.extent_type_id) Type,
         (select value from enumeration_value where id = e.portion_id)  Portion,
         people.sort_name Name,
         people.address_1 Address,
         people.city City,
         people.region Region,
         people.post_code 'Post Code',
         people.role Role,
         d.`begin` "Begin date",
         d.`end` "End date", 
         (select value from enumeration_value where id=d.date_type_id) "Date Type",
         (select value from enumeration_value where id=d.label_id) "Label ID", 
         d.`expression` "Date expression",
         bulk.`begin` "Bulk begin",
         bulk.`end` "Bulk end", 
         (select value from enumeration_value where id=bulk.date_type_id) "Bulk date type",
         (select value from enumeration_value where id=bulk.label_id) "Label ID", 
         bulk.`expression` "Bulk expression",
         acc.repo_id "Aspace repo id",
         acc.id "Aspace accession id",
         acc.identifier "Aspace Identifier",
         @num_elements := LENGTH(acc.identifier) - LENGTH(REPLACE(acc.identifier, '"', '')) AS num_elements,        
         SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 2), '"', -1) AS id1,
         SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 4), '"', -1) AS id2,
           IF(@num_elements > 4, SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 6), '"', -1), '') AS id3,
           IF(@num_elements > 6, SUBSTRING_INDEX(SUBSTRING_INDEX(acc.identifier, '"', 8), '"', -1), '') AS id4
from 
   accession acc inner join user_defined ud on ud.accession_id = acc.id
   join extent e on e.accession_id = acc.id
   join linked_agents_rlshp lar on lar.accession_id = acc.id
   join name_person np on lar.agent_person_id = np.id
   join agent_contact ac on lar.agent_person_id = ac.agent_person_id
   join date d on d.accession_id = acc.id
   left join (select * from date d 
    where (select value from enumeration_value where id=d.date_type_id) = "bulk") bulk on bulk.accession_id = acc.id
   join (select larlship.accession_id, (select value from enumeration_value where id=larlship.role_id) role, np.sort_name, address_1, city, region, post_code 
      from agent_contact ac 
         join name_person np on ac.agent_person_id = np.agent_person_id
          join linked_agents_rlshp larlship on larlship.agent_person_id = ac.agent_person_id
          where larlship.role_id in(880,881)
      union
      select larlship.accession_id, (select value from enumeration_value where id=larlship.role_id) role, nce.sort_name, address_1, city, region, post_code 
      from agent_contact ac 
         join name_corporate_entity nce on ac.agent_corporate_entity_id = nce.agent_corporate_entity_id
          join linked_agents_rlshp larlship on larlship.agent_corporate_entity_id = ac.agent_corporate_entity_id
          where larlship.role_id in(880,881)
      union
      select larlship.accession_id, (select value from enumeration_value where id=larlship.role_id) role, nf.sort_name, address_1, city, region, post_code 
      from agent_contact ac 
         join name_family nf on ac.agent_family_id = nf.agent_family_id
          join linked_agents_rlshp larlship on larlship.agent_family_id = ac.agent_family_id
          where larlship.role_id in(880,881)) people 
      ON people.accession_id = acc.id
where ((select value from enumeration_value where id=d.date_type_id) = "inclusive"
    or (select value from enumeration_value where id=d.date_type_id) = "single")
    $where_clause

ORDER BY ud.string_1

EOQ;

  return $query;
}