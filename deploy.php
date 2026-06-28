<?php
namespace Deployer;

require 'recipe/laravel.php';

// Config

set('repository', 'git@github.com:janakkapadia/jackman.git');

add('shared_files', ['.env', 'database/database.sqlite']);
add('shared_dirs', [
    'storage',
    'bootstrap/cache',
]);
add('writable_dirs', []);

// Hosts
host('prod')
    ->set('hostname', '65.20.68.173')
    ->set('remote_user', 'deployer')
    ->set('deploy_path', '~/jackman')
    ->set('branch', 'main')
    ->set('keep_releases', 1);

desc('Initial server setup');
task('server:setup', function () {

    // Update packages
    run('apt-get update');

    // PHP extensions
    run('apt-get install -y php8.3-redis');

    // Redis
    run('apt-get install -y redis-server');

    // Supervisor
    run('apt-get install -y supervisor');

    // Enable services
    run('systemctl enable redis-server');
    run('systemctl start redis-server');

    run('systemctl enable supervisor');
    run('systemctl start supervisor');

    run('mkdir -p {{deploy_path}}/shared/storage/logs');

    $config = <<<'SUPERVISOR'
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/deployer/jackman/current/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deployer
numprocs=2
redirect_stderr=true
stdout_logfile=/home/deployer/jackman/shared/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISOR;

    run("echo '$config' | sudo tee /etc/supervisor/conf.d/laravel-worker.conf > /dev/null");



    run('sudo supervisorctl reread');
    run('sudo supervisorctl update');
    // Setup cron for Laravel scheduler
    run('(crontab -l 2>/dev/null; echo "* * * * * cd {{current_path}} && php artisan schedule:run >> /dev/null 2>&1") | crontab -');
});

task('queue:restart', function () {
    run('{{bin/php}} {{current_path}}/artisan queue:restart');
});


// Node build task
task('npm:install', function () {
    run('cd {{release_path}} && npm install');
});

task('npm:build', function () {
    run('cd {{release_path}} && {{bin/php}} artisan optimize:clear');
    run('cd {{release_path}} && {{bin/php}} artisan wayfinder:generate');
    run('cd {{release_path}} && npm run build');
});

// Run after vendors are installed
after('deploy:vendors', 'npm:install');
after('npm:install', 'npm:build');
after('deploy:cleanup', 'queue:restart');


// Hooks
after('deploy:failed', 'deploy:unlock');
