<?php

namespace KhaledHajSalem\ZatcaPhase2\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use KhaledHajSalem\ZatcaPhase2\Traits\HasZatcaSupport;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class InvoiceModel extends Model
{
    use HasZatcaSupport, MockeryPHPUnitIntegration;

    protected $table = 'invoices';
    protected $guarded = [];
    public $timestamps = false;

    /**
     * Create a new model instance with the given attributes.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Set the attributes directly - this is just for testing
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }

        // Initialize HasZatcaSupport trait
        if (method_exists($this, 'initializeHasZatcaSupport')) {
            $this->initializeHasZatcaSupport();
        }
    }

    /**
     * Override to allow direct property access for testing.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        if (isset($this->$key)) {
            return $this->$key;
        }

        return parent::__get($key);
    }

    /**
     * Override to allow direct property setting for testing.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->$key = $value;
    }
}