<style type="text/css">
    #poststuff .inside {
        margin: 20px 5px;
    }
	#save-gather-ua-jobs-settings-button {
		width: 160px;
	}
	.form-table th,
	.form-table td {
		vertical-align: top;
		padding: 0 10px 25px 0;
	}
	.form-table td {
		padding-top: 5px;
		padding-left: 10px;
	}
	.form-table tr:last-child th,
	.form-table tr:last-child td {
		padding-bottom: 0;
	}
	.form-table input[type="text"] {
		width: 100%;
	}
	.refresh-button {
        float: right;
        margin: 0 10px 15px 20px !important;
	}
    .refresh-button + p {
        padding-top: 5px;
    }
	.refreshed-message {
        padding-top: 0 !important;
		color: #009f3f;
	}
	.subsection-header {
		margin: 1.5em 0 0.5em 0;
		color: #777;
		font-weight: normal;
	}
	ul.ua-faculty-jobs {
        clear: both;
		list-style: disc outside;
		margin-left: 0;
	}
	ul.ua-faculty-jobs li {
		margin: 0 0 10px 0;
		margin-left: 30px;
	}
	ul.ua-faculty-jobs ul {
		list-style: circle outside;
		margin-left: 30px;
	}
	ul.ua-faculty-jobs ul li {
		margin-bottom: 2px;
	}
	ul.ua-faculty-jobs li.keyword-jobs.highlight {
		background: rgba( 255, 255, 0, 0.07 );
		padding: 15px;
		list-style: none;
		margin-left: 0;
	}
	.title {
		font-weight: bold;
	}
	.highlight-keyword {
		background: yellow;
	}
</style>

<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

    <div id="poststuff">

        <div id="post-body" class="metabox-holder columns-2">

            <div id="postbox-container-1" class="postbox-container"><?php

                do_meta_boxes( 'gather-ua-jobs-tools', 'side', array() );

            ?></div> <!-- #postbox-container-1 -->

            <div id="postbox-container-2" class="postbox-container"><?php

                do_meta_boxes( 'gather-ua-jobs-tools', 'normal', array() );

            ?></div>

        </div> <!-- #post-body -->

    </div> <!-- #poststuff -->

</div> <!-- .wrap -->