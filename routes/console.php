<?php

use Illuminate\Support\Facades\Schedule;

// A safety net rather than a routine: levels are only written to disk as part
// of saving one, and that deletes the file again if the row cannot be written.
// This catches an image whose row was lost some other way — a crash between the
// two, a restore from an older database — so weekly is often enough.
Schedule::command('levels:prune')->weekly();
