<?php

class ConstructionStages
{
	private $db;

	public function __construct()
	{
		$this->db = Api::getDb();
	}

	public function getAll()
	{
		$stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
		");
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getSingle($id)
	{
		$stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
			WHERE ID = :id
		");
		$stmt->execute(['id' => $id]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

    /**
     * @throws Exception
     */
    public function post(ConstructionStagesCreate $data)
	{

        $checkErrors = $this->validateFields($data);
        if($checkErrors['errors'] != []){
            throw new Exception(json_encode($checkErrors['errors']));
        }
        $duration = null;
        if ($data->endDate !== null) {

            $startDateTime = new DateTime($data->startDate);
            $endDateTime = new DateTime($data->endDate);
            $interval = $startDateTime->diff($endDateTime);
            $duration = $interval->days;
        }
		$stmt = $this->db->prepare("
			INSERT INTO construction_stages
			    (name, start_date, end_date, duration, durationUnit, color, externalId, status)
			    VALUES (:name, :start_date, :end_date, :duration, :durationUnit, :color, :externalId, :status)
			");
		$stmt->execute([
			'name' => $data->name,
			'start_date' => $data->startDate,
			'end_date' => $data->endDate,
			'duration' => $duration,
			'durationUnit' => $data->durationUnit,
			'color' => $data->color,
			'externalId' => $data->externalId,
			'status' => $data->status,
		]);
		return $this->getSingle($this->db->lastInsertId());
	}

    /**
     * Update a construction stage by ID using the provided data.
     *
     * @param ConstructionStagesUpdate $data The data used to update the construction stage.
     * @param int                      $id   The ID of the construction stage to update.
     *
     * @return mixed Returns the updated construction stage object or the result of the update operation.
     *               The specific return value may vary based on your implementation.
     * @throws Exception If an error occurs during the update process.
     */
    public function patch(ConstructionStagesUpdate $data, $id)
    {
        $checkErrors = $this->validateFields($data);
        if($checkErrors['errors'] != []){
            throw new Exception(json_encode($checkErrors['errors']));
        }
        $fieldMappings = [
            'name' => 'name',
            'startDate' => 'start_date',
            'endDate' => 'end_date',
            'duration' => 'duration',
            'durationUnit' => 'durationUnit',
            'color' => 'color',
            'externalId' => 'externalId',
            'status' => 'status',
        ];
        $query = "UPDATE construction_stages SET ";
        $bindings = [];
        foreach($data as $field => $value){
            if(!empty($value) && isset($fieldMappings[$field])){
                $columnName = $fieldMappings[$field];
                $query .= "$columnName = :$columnName, ";
                $bindings[$columnName] = $value;

              }
            if ($field === 'status' && !in_array($value, ['PLANNED', 'NEW', 'DELETED'])) {
                throw new Exception("Invalid status value: $value, please check the state field");
            }
        }
        $query = rtrim($query, ', ');
        $query .= " WHERE ID = :id";
        $bindings['id'] = $id;
        $stmt = $this->db->prepare($query);
        foreach ($bindings as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $stmt->execute();
        return $this->getSingle($id);

    }
    /**
     * Delete a construction stage by ID: actually change the status to 'DELETED'.
     *
     * @param int $id The ID of the construction stage to delete.
     *
     * @return mixed Returns the result of the deletion operation.
     *
     * @throws Exception If an error occurs during the deletion process.
     */
    public function deleteConstructionStage($id)
    {
        $query = "UPDATE construction_stages
        SET status = 'DELETED'
        WHERE ID = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        // Check if any rows were affected
        if ($stmt->rowCount() === 0) {
            throw new Exception("Construction stage not found");
        }

        return "Construction stage with ID $id has been deleted";
    }

    /**
     * Validate the posted fields against a set of rules.
     *
     * @param array $data The posted fields to validate.
     *
     * @return array An associative array containing the validated data and any errors.
     *               The 'data' key holds the validated data, and the 'errors' key holds any validation errors.
     * @throws Exception
     */
    public function validateFields($data){
        $rules = [
            'name' => [
                'max_length' => 255,
            ],
            'startDate' => [
                //'date_format' => 'Y-m-d\TH:i:s\Z',
                'iso_8601' => true,
            ],
            'endDate' => [
                'nullable' => true,
                //'date_format' => 'Y-m-d\TH:i:s\Z',
                'later_than' => 'start_date',
                'iso_8601' => true,
            ],
            'duration' => [
                'skip' => true,
            ],
            'durationUnit' => [
                'allowed_values' => ['HOURS', 'DAYS', 'WEEKS'],
                'default' => 'DAYS',
            ],
            'color' => [
                'nullable' => true,
                'hex_color' => true,
            ],
            'externalId' => [
                'nullable' => true,
                'max_length' => 255,
            ],
            'status' => [
                'allowed_values' => ['NEW', 'PLANNED', 'DELETED'],
                'default' => 'NEW',
            ],
        ];




        $fieldMappings = [
            'name' => 'name',
            'start_date' => 'startDate',
            'endDate' => 'end_date',
            'duration' => 'duration',
            'durationUnit' => 'durationUnit',
            'color' => 'color',
            'externalId' => 'externalId',
            'status' => 'status',
        ];
        $errors = [];

        foreach ($rules as $field => $fieldRules){
            if(isset($data->$field) ){
               $value = $data->$field;
               foreach($fieldRules as $rule => $param){

                   switch($rule){
                       case 'max_length':
                           if(strlen($value) > $param){
                               $errors[] = "Field $field must be less than $param characters long";
                           }
                           break;

                       case 'iso_8601':

                           $dateTime = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $value);
                           if($dateTime === false || $dateTime->format('Y-m-d\TH:i:s\Z') !== $value){
                               $errors[$field] = "Field '$field' must be a valid ISO 8601 date and time.";
                           }

                           break;
                          case 'nullable':
                              if(!$param && empty($value)){
                                  $errors[] = "Field $field cannot be empty";
                              }
                              break;
                       case 'later_than':

                           $mapping = $fieldMappings[$param];


                           if (!empty($value) && isset($data->$mapping)) {
                               $startDateTime = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $data->$mapping);
                               $endDateTime = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $value);

                               if ($endDateTime <= $startDateTime) {
                                   $errors[$field][] = "Field '$field' must be a datetime later than the '$param' field.";
                               }
                           }
                           break;
                       case 'allowed_values':
                           if (!in_array($value, $param, true)) {
                               $allowedValues = implode(', ', $param);
                               $errors[$field] = "Field '$field' must be one of the allowed values: $allowedValues.";
                           }
                           break;
                       case 'hex_color':
                           if (!empty($value) && !preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
                               $errors[$field] = "Field '$field' must be a valid HEX color code.";
                           }
                           break;
                   }
               }
            }

        }

        return ['data' => $data, 'errors' => $errors];
    }
}