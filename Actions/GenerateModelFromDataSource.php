<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\TaskResultInterface;
use exface\Core\Factories\TaskResultFactory;

/**
 * This action runs one or more selected test steps
 *
 * @author Andrej Kabachnik
 *        
 */
class GenerateModelFromDataSource extends AbstractAction
{

    protected function init()
    {
        $this->setIcon(Icons::COGS);
        $this->setInputRowsMin(1);
        $this->setInputRowsMax(null);
    }

    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : TaskResultInterface
    {
        $input_data = $this->getInputDataSheet($task);
        
        if (! $input_data->getMetaObject()->is('exface.Core.MODEL_BUILDER_INPUT')) {
            throw new ActionInputInvalidObjectError($this, 'Action "' . $this->getAlias() . '" exprects exface.Core.MODEL_BUILDER_INPUT as input, "' . $this->getInputDataSheet()->getMetaObject()->getAliasWithNamespace() . '" given instead!');
        }
        
        $obj_col = $input_data->getColumns()->getByExpression('OBJECT');
        $data_src_col = $input_data->getColumns()->getByExpression('DATA_SOURCE');
        $message = '';
        $created = 0;
        $skipped = 0;
        if ($obj_col && ! $obj_col->isEmpty(true)) {
            
            foreach ($input_data->getRows() as $row){
                $data_source = $this->getWorkbench()->data()->getDataSource($row[$data_src_col->getName()]);
                $model_builder = $data_source->getConnection()->getModelBuilder();
                
                $created_ds = $model_builder->generateAttributesForObject($this->getWorkbench()->model()->getObject($row['OBJECT']));
                $created += $created_ds->countRows();
                $skipped += $created_ds->countRowsAll() - $created_ds->countRows();
            }
            
            $message .= 'Created ' . $created . ' attributes, ' . $skipped . ' skipped as duplicates.';
            
        } elseif ($data_src_col && ! $data_src_col->isEmpty()) {
            
            foreach ($input_data->getRows() as $row){
                $data_source = $this->getWorkbench()->data()->getDataSource($row[$data_src_col->getName()]);
                $app = $this->getWorkbench()->getApp($row['APP']);
                $model_builder = $data_source->getConnection()->getModelBuilder();
                
                $created_ds = $model_builder->generateObjectsForDataSource($app, $data_source, $row['OBJECT_DATA_ADDRESS_MASK']);
                $created += $created_ds->countRows();
                $skipped += $created_ds->countRowsAll() - $created_ds->countRows();
            }
            
            $message .= 'Created ' . $created . ' objects, ' . $skipped . ' skipped as duplicates.';
        }
        
        return TaskResultFactory::createMessageResult($task, $message);
    }
}
?>