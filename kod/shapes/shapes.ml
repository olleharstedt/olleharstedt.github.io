module Surface = struct
    type t
end

let draw_shape (s : shape) (surface : Surface.t) = 
	()

module type SHAPE_OPERATION = sig
	type result
	val run : shape -> result
end

module ShapeArea : SHAPE_OPERATION = struct
	type result = int
	let run s = match s with
		| Point _ -> 0
end

