<?php $this->partial('header-html'); ?>
	<?php $this->partial('header'); ?>

		<section class="section section-form">
			<div class="inner boxfix-vert">
				<div class="margins">
					<div class="the-content">
						<?php $site->partial('payments/product', ['form' => $form, 'order' => $order]); ?>
						<!--  -->
						<?php $site->partial('payments/payment', ['form' => $form, 'order' => $order, 'processors' => $processors]); ?>
					</div>
				</div>
			</div>
		</section>

	<?php $this->partial('footer'); ?>
<?php $this->partial('footer-html'); ?>