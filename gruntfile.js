module.exports = function( grunt ) {
	grunt.initConfig( {
		compress: {
			main: {
				options: {
					archive: 'simple-comment-editing.zip',
				},
				files: [
					{ src: [ 'simple-comment-editing.php' ], dest: '/', filter: 'isFile' }, // includes files in path
					{ src: [ 'index.php' ], dest: '/', filter: 'isFile' }, // includes files in path
					{ src: [ 'readme.txt' ], dest: '/', filter: 'isFile' }, // includes files in path
					{ src: [ 'uninstall.php' ], dest: '/', filter: 'isFile' }, // includes files in path
					{ src: [ 'images/**' ], dest: '/' }, // includes files in path and its subdirs
					{ src: [ 'includes/**' ], dest: '/' }, // includes files in path and its subdirs
					{ src: [ 'includes/**' ], dest: '/' }, // includes files in path and its subdirs
					{ src: [ 'languages/simple-comment-editing.pot' ], dest: '/' }, // includes the translation template
					{ src: [ 'js/**' ], dest: '/' }, // includes files in path and its subdirs
					{ src: [ 'dist/**' ], dest: '/' }, // includes files in path and its subdirs
					{ src: [ 'lib/**' ], dest: '/' }, // includes files in path and its subdirs
				],
			},
		},
	} );
	grunt.registerTask( 'make-pot', 'Generate the translation template.', function() {
		const done = this.async();
		grunt.util.spawn(
			{
				cmd: 'npm',
				args: [ 'run', 'i18n:pot' ],
				opts: { stdio: 'inherit' },
			},
			function( error ) {
				done( ! error );
			}
		);
	} );
	grunt.registerTask( 'default', [ 'make-pot', 'compress' ] );

	grunt.loadNpmTasks( 'grunt-contrib-compress' );
};
