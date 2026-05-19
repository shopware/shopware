# Scheduled Task System

The Scheduled Task system provides a robust framework for executing recurring background tasks in Shopware. It supports reliable scheduling, execution tracking, error handling, and automatic recovery mechanisms.

## Architecture Overview

The system consists of several key components:

### Core Components

- **[ScheduledTask](ScheduledTask.php)** - Abstract base class for all scheduled tasks
- **[ScheduledTaskEntity](ScheduledTaskEntity.php)** - Entity representing task persistence in database
- **[ScheduledTaskDefinition](ScheduledTaskDefinition.php)** - DAL definition with status constants
- **[ScheduledTaskHandler](ScheduledTaskHandler.php)** - Abstract handler for task execution
- **[TaskScheduler](Scheduler/TaskScheduler.php)** - Manages task queueing and scheduling logic
- **[TaskRunner](Scheduler/TaskRunner.php)** - Handles direct task execution
- **[TaskRegistry](Registry/TaskRegistry.php)** - Manages task registration and lifecycle

### Support Components

- **[RegisterScheduledTaskMessage](MessageQueue/RegisterScheduledTaskMessage.php)** - Message for task registration
- **[RegisterScheduledTaskHandler](MessageQueue/RegisterScheduledTaskHandler.php)** - Handles task registration
- **[ScheduleProvider](SymfonyBridge/ScheduleProvider.php)** - Symfony Scheduler integration (experimental)

## Task States and Lifecycle

### Available States

1. **`scheduled`** - Task is ready to be queued when its execution time arrives
2. **`queued`** - Task has been dispatched to the message queue
3. **`running`** - Task is currently being executed by a worker
4. **`failed`** - Task execution failed and won't be rescheduled (unless `shouldRescheduleOnFailure()` returns true)
5. **`skipped`** - Task was skipped due to configuration or conditions
6. **`inactive`** - Task is disabled and won't be executed

## State Transition Details

### Initial Registration
- **Service**: `TaskRegistry::registerTasks()`
- **Trigger**: Application startup or task registration
- **New State**: `scheduled` if `shouldRun()` returns true, otherwise `skipped`

### Scheduled → Queued
- **Service**: `TaskScheduler::queueScheduledTasks()`
- **Trigger**: Task's `nextExecutionTime` has passed
- **Conditions**: Task status is `scheduled` or `skipped`, and `shouldRun()` returns true
- **Action**: Updates status to `queued`, dispatches task to message bus

### Queued → Running
- **Service**: `ScheduledTaskHandler::__invoke()`
- **Trigger**: Message queue worker picks up the task
- **Action**: Updates status to `running` before execution begins

### Running → Scheduled (Success)
- **Service**: `ScheduledTaskHandler::rescheduleTask()`
- **Trigger**: Task execution completes successfully
- **Action**: Updates status to `scheduled`, sets `lastExecutionTime`, calculates new `nextExecutionTime`

### Running → Failed
- **Service**: `ScheduledTaskHandler::markTaskFailed()`
- **Trigger**: Task execution throws exception and `shouldRescheduleOnFailure()` returns false
- **Action**: Updates status to `failed`

### Running → Scheduled (Failed but Reschedule)
- **Service**: `ScheduledTaskHandler::rescheduleTask()`
- **Trigger**: Task execution throws exception but `shouldRescheduleOnFailure()` returns true
- **Action**: Logs error, reschedules task for next execution

### Recovery Mechanism
- **Service**: `TaskScheduler::queueScheduledTasks()`
- **Trigger**: Tasks stuck in `queued` or `running` state for more than 12 hours
- **Action**: Re-queues tasks assuming worker crashed or message was lost

### Manual Operations
- **Service**: `TaskRegistry::scheduleTask()` - Force schedule a task
- **Service**: `TaskRegistry::deactivateTask()` - Deactivate a task
- **Service**: `TaskRunner::runSingleTask()` - Execute task immediately

## Key Features

### Execution Safety
- **Concurrent Execution Protection**: Database-level locking prevents multiple executions
- **Stuck Task Recovery**: Automatically recovers tasks stuck for more than 12 hours
- **Execution Validation**: `isExecutionAllowed()` prevents execution in invalid states

### Error Handling
- **Configurable Retry**: Tasks can opt into rescheduling on failure via `shouldRescheduleOnFailure()`
- **Error Logging**: Failed executions are logged with context
- **Manual Recovery**: Failed tasks can be manually rescheduled

### Flexible Scheduling
- **Predefined Intervals**: Constants for MINUTELY, HOURLY, DAILY, WEEKLY
- **Custom Intervals**: Define any interval in seconds
- **Conditional Execution**: `shouldRun()` method allows environment-based execution control
- **Dynamic Scheduling**: Runtime modification of intervals and scheduling

### Administrative Control
- **Manual Execution**: Force immediate execution via `TaskRunner::runSingleTask()`
- **Task Activation/Deactivation**: Enable/disable tasks via `TaskRegistry`
- **Status Monitoring**: Track execution history and current state

## Integration Points

### Message Queue Integration
Tasks are dispatched through Symfony Messenger, allowing for:
- Async execution
- Multiple transport options
- Worker scaling
- Message persistence

### Symfony Scheduler Integration (Experimental)
The `ScheduleProvider` enables integration with Symfony's Scheduler component for more advanced scheduling features.

### CLI Commands

The following CLI commands are available for managing scheduled tasks:

#### `scheduled-task:list`
Lists all registered scheduled tasks with their status and timing information.
```bash
php bin/console scheduled-task:list
```
**Output**: Table showing name, next execution, last execution, run interval, and status for each task.

#### `scheduled-task:register`
Registers all scheduled tasks found in the system. Typically run during deployment or after adding new tasks.
```bash
php bin/console scheduled-task:register
```

#### `scheduled-task:run`
Runs the scheduled task runner daemon that continuously queues tasks when their execution time arrives.
```bash
php bin/console scheduled-task:run [options]
```
**Options**:
- `--memory-limit|-m`: Memory limit the worker can consume
- `--time-limit|-t`: Time limit in seconds the worker can run  
- `--no-wait`: Queue current tasks once and exit (don't run continuously)

#### `scheduled-task:run-single <taskName>`
Executes a specific task immediately, regardless of its schedule.
```bash
php bin/console scheduled-task:run-single log_entry.cleanup
```
**Arguments**: 
- `taskName`: The task name (e.g., 'log_entry.cleanup', 'theme.delete_files')

#### `scheduled-task:schedule <taskName>`
Manually schedules a task for execution.
```bash
php bin/console scheduled-task:schedule log_entry.cleanup [options]
```
**Options**:
- `--force|-f`: Force scheduling even if task is currently running/queued
- `--immediately|-i`: Set next execution time to now

#### `scheduled-task:deactivate <taskName>`
Deactivates a scheduled task to prevent future executions.
```bash
php bin/console scheduled-task:deactivate log_entry.cleanup [options]
```
**Options**:
- `--force|-f`: Force deactivation even if task is currently running/queued

**Warning**: This only prevents future scheduling; it won't cancel a currently running execution.

## Monitoring and Debugging

### Execution Tracking
- Monitor `lastExecutionTime` and `nextExecutionTime` fields
- Check task status for execution state
- Review logs for error details

### Common Issues
1. **Stuck Tasks**: Check for tasks in `running` or `queued` state for extended periods, they should get automatically re-queued after they are stuck for 12 hours.
2. **Failed Tasks**: Review error logs and consider `shouldRescheduleOnFailure()` setting
3. **Skipped Tasks**: Verify `shouldRun()` conditions and environment configuration